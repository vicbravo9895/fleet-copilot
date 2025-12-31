<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use App\Models\Vehicle;
use App\Neuron\Tools\Concerns\FlexibleVehicleSearch;
use App\Samsara\Client\SamsaraClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class GetDashcamMedia extends Tool
{
    use FlexibleVehicleSearch;
    /**
     * Storage disk for media files.
     */
    private const STORAGE_DISK = 'public';

    /**
     * Storage path prefix for dashcam media.
     */
    private const STORAGE_PATH = 'dashcam-media';

    /**
     * Human-readable descriptions for media types.
     */
    private const MEDIA_TYPE_DESCRIPTIONS = [
        'dashcamRoadFacing' => 'Cámara frontal (hacia la carretera)',
        'dashcamDriverFacing' => 'Cámara interior (hacia el conductor)',
        'photo' => 'Fotografía',
        'video' => 'Video',
    ];

    public function __construct()
    {
        parent::__construct(
            'GetDashcamMedia',
            'Obtener imágenes y videos recientes de las cámaras de dashcam de uno o varios vehículos. ' .
            'Incluye imágenes de la cámara frontal (dashcamRoadFacing) y la cámara interior (dashcamDriverFacing). ' .
            'Busca automáticamente las imágenes más recientes disponibles, retrocediendo en el tiempo si es necesario.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'vehicle_ids',
                type: PropertyType::STRING,
                description: 'IDs de los vehículos a consultar, separados por coma. Ejemplo: "123456789,987654321"',
                required: false,
            ),
            new ToolProperty(
                name: 'vehicle_names',
                type: PropertyType::STRING,
                description: 'Nombres de los vehículos a consultar, separados por coma. Se buscarán en la base de datos para obtener sus IDs. Ejemplo: "Camión 1, TR-601"',
                required: false,
            ),
            new ToolProperty(
                name: 'media_types',
                type: PropertyType::STRING,
                description: 'Tipos de media a obtener, separados por coma. Opciones: dashcamRoadFacing (cámara frontal), dashcamDriverFacing (cámara conductor). Por defecto obtiene ambos tipos.',
                required: false,
            ),
            new ToolProperty(
                name: 'max_search_minutes',
                type: PropertyType::INTEGER,
                description: 'Tiempo máximo hacia atrás para buscar imágenes (en minutos). Por defecto es 60 minutos. Máximo 1440 minutos (24 horas).',
                required: false,
            ),
        ];
    }

    public function __invoke(
        ?string $vehicle_ids = null,
        ?string $vehicle_names = null,
        ?string $media_types = null,
        int $max_search_minutes = 60
    ): string {
        try {
            $vehicleIds = [];
            $vehicleNamesMap = []; // Map samsara_id => vehicle name for better output

            // Resolve vehicle IDs from names if provided (using flexible search)
            if ($vehicle_names) {
                $result = $this->resolveVehicleNamesFlexible($vehicle_names);
                $vehicleIds = $result['vehicleIds'];
                $vehicleNamesMap = $result['vehicleNamesMap'];
                
                // If we have suggestions but no exact matches, ask for clarification
                if (empty($vehicleIds) && !empty($result['suggestions'])) {
                    return $this->generateClarificationResponse($result['suggestions']);
                }

                if (empty($vehicleIds)) {
                    return json_encode([
                        'error' => true,
                        'message' => 'No se encontraron vehículos con los nombres especificados: ' . $vehicle_names,
                    ], JSON_UNESCAPED_UNICODE);
                }
            }

            // Add directly provided IDs
            if ($vehicle_ids) {
                $ids = array_map('trim', explode(',', $vehicle_ids));
                foreach ($ids as $id) {
                    if (!in_array($id, $vehicleIds)) {
                        $vehicleIds[] = $id;
                        // Try to get name from database
                        $vehicle = Vehicle::where('samsara_id', $id)->first();
                        if ($vehicle) {
                            $vehicleNamesMap[$id] = $vehicle->name;
                        }
                    }
                }
            }

            // Parse media types
            $types = [];
            if ($media_types) {
                $types = array_map('trim', explode(',', $media_types));
                // Validate types
                $validTypes = array_keys(self::MEDIA_TYPE_DESCRIPTIONS);
                $types = array_filter($types, fn($type) => in_array($type, $validTypes));
            }

            // Default to both dashcam types
            if (empty($types)) {
                $types = ['dashcamRoadFacing', 'dashcamDriverFacing'];
            }

            // Limit search range
            $maxRetries = min(
                (int) ceil($max_search_minutes / 5),
                288 // Max 24 hours at 5-minute increments
            );

            // Fetch media from API with retry logic
            $client = new SamsaraClient();
            $response = $client->getDashcamMediaWithRetry(
                vehicleIds: $vehicleIds,
                inputs: $types,
                maxRetries: $maxRetries,
                incrementMinutes: 5
            );

            // Process and format the response
            $formattedResult = $this->formatAndPersistMedia(
                $response,
                $vehicleNamesMap
            );

            return json_encode($formattedResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return json_encode([
                'error' => true,
                'message' => 'Error al obtener media de dashcam: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Format the media response and persist files to storage.
     */
    protected function formatAndPersistMedia(array $response, array $vehicleNamesMap): array
    {
        $meta = $response['meta'] ?? [];
        $data = $response['data'] ?? [];

        $result = [
            'found' => $meta['found'] ?? false,
            'search_info' => [
                'attempts' => $meta['attempts'] ?? 0,
                'range_minutes' => $meta['searchRangeMinutes'] ?? 0,
                'start_time' => $meta['startTime'] ?? null,
                'end_time' => $meta['endTime'] ?? null,
            ],
            'total_media' => count($data),
            'media' => [],
        ];

        if (empty($data)) {
            $result['message'] = 'No se encontraron imágenes o videos en el rango de tiempo especificado. ' .
                'Es posible que las dashcams no hayan capturado media recientemente o que los vehículos ' .
                'no tengan dashcams instaladas.';
            return $result;
        }

        // Group media by vehicle
        $mediaByVehicle = [];
        foreach ($data as $mediaItem) {
            // API returns vehicleId directly, not in a nested vehicle object
            $vehicleId = $mediaItem['vehicleId'] ?? 'unknown';
            if (!isset($mediaByVehicle[$vehicleId])) {
                // Try to get vehicle name from map or database
                $vehicleName = $vehicleNamesMap[$vehicleId] ?? null;
                if (!$vehicleName) {
                    $vehicle = Vehicle::where('samsara_id', $vehicleId)->first();
                    $vehicleName = $vehicle?->name ?? 'Vehículo desconocido';
                }
                
                $mediaByVehicle[$vehicleId] = [
                    'vehicleId' => $vehicleId,
                    'vehicleName' => $vehicleName,
                    'media' => [],
                ];
            }

            // Process this media item
            $processedMedia = $this->processMediaItem($mediaItem, $vehicleId);
            if ($processedMedia) {
                $mediaByVehicle[$vehicleId]['media'][] = $processedMedia;
            }
        }

        // Format for output
        foreach ($mediaByVehicle as $vehicleData) {
            // Sort media by timestamp (newest first)
            usort($vehicleData['media'], function ($a, $b) {
                return strtotime($b['timestamp'] ?? '') - strtotime($a['timestamp'] ?? '');
            });

            // Group by type
            $byType = [];
            foreach ($vehicleData['media'] as $media) {
                $type = $media['type'] ?? 'unknown';
                if (!isset($byType[$type])) {
                    $byType[$type] = [];
                }
                $byType[$type][] = $media;
            }

            $result['media'][] = [
                'vehicleId' => $vehicleData['vehicleId'],
                'vehicleName' => $vehicleData['vehicleName'],
                'totalItems' => count($vehicleData['media']),
                'byType' => $byType,
                // Card data for frontend
                '_cardData' => $this->generateCardData($vehicleData),
            ];
        }

        // Add usage hint
        $result['_hint'] = 'Para mostrar las imágenes de dashcam, usa el bloque :::dashcamMedia con los datos de _cardData.';

        return $result;
    }

    /**
     * Process a single media item, downloading and persisting if needed.
     * 
     * API response structure:
     * - input: "dashcamRoadFacing" | "dashcamDriverFacing"
     * - mediaType: "image" | "video"
     * - startTime: ISO timestamp
     * - urlInfo.url: The media URL
     * - vehicleId: The vehicle ID
     */
    protected function processMediaItem(array $mediaItem, string $vehicleId): ?array
    {
        // API uses 'input' for the camera type (dashcamRoadFacing, dashcamDriverFacing)
        $inputType = $mediaItem['input'] ?? null;
        // API uses 'urlInfo.url' for the actual URL
        $mediaUrl = $mediaItem['urlInfo']['url'] ?? null;
        // API uses 'startTime' for the timestamp
        $timestamp = $mediaItem['startTime'] ?? null;
        // No explicit ID, so we generate one from the URL path
        $mediaId = $this->extractMediaIdFromUrl($mediaUrl);

        if (!$mediaUrl || !$inputType) {
            return null;
        }

        // Generate unique filename based on vehicle, input type, and timestamp
        $uniqueKey = $this->generateUniqueKey($vehicleId, $inputType, $timestamp, $mediaId);
        $extension = $this->getExtensionFromType($mediaItem['mediaType'] ?? 'image', $mediaUrl);
        $filename = "{$uniqueKey}.{$extension}";
        $storagePath = self::STORAGE_PATH . "/{$vehicleId}/{$filename}";

        // Check if already persisted
        $localUrl = null;
        $isPersisted = false;
        
        if (Storage::disk(self::STORAGE_DISK)->exists($storagePath)) {
            // Already exists, use existing file
            $localUrl = Storage::disk(self::STORAGE_DISK)->url($storagePath);
            $isPersisted = true;
        } else {
            // Download and persist
            try {
                $localUrl = $this->downloadAndPersist($mediaUrl, $storagePath);
                $isPersisted = true;
            } catch (\Exception $e) {
                // If download fails, still return the original URL
                $localUrl = $mediaUrl;
            }
        }

        return [
            'id' => $mediaId,
            'type' => $inputType,
            'typeDescription' => self::MEDIA_TYPE_DESCRIPTIONS[$inputType] ?? $inputType,
            'mediaType' => $mediaItem['mediaType'] ?? 'image',
            'timestamp' => $timestamp,
            'originalUrl' => $mediaUrl,
            'localUrl' => $localUrl,
            'isPersisted' => $isPersisted,
            'storagePath' => $storagePath,
            'triggerReason' => $mediaItem['triggerReason'] ?? null,
        ];
    }

    /**
     * Extract a unique ID from the media URL.
     */
    protected function extractMediaIdFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        
        // Extract the unique part from the S3 URL path
        // Example: .../1767077596573/CXex5fBwvN-camera-still-driver-1767077597073.lepton.jpeg
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            // Get the filename part
            $filename = basename($path);
            // Remove extension and create a hash
            $name = pathinfo($filename, PATHINFO_FILENAME);
            return substr(md5($name), 0, 12);
        }
        
        return substr(md5($url), 0, 12);
    }

    /**
     * Generate a unique key for the media file.
     * Format: {vehicleId}_{type}_{timestamp}_{mediaId}
     */
    protected function generateUniqueKey(
        string $vehicleId,
        string $mediaType,
        ?string $timestamp,
        ?string $mediaId
    ): string {
        // Create a deterministic unique key
        $parts = [
            $vehicleId,
            $mediaType,
        ];

        // Add timestamp (formatted for filename safety)
        if ($timestamp) {
            $dateTime = new \DateTime($timestamp);
            $parts[] = $dateTime->format('Y-m-d_H-i-s');
        }

        // Add media ID if available for extra uniqueness
        if ($mediaId) {
            $parts[] = substr(md5($mediaId), 0, 8);
        }

        return implode('_', $parts);
    }

    /**
     * Get file extension based on media type and URL.
     * 
     * @param string $mediaType The media type ('image' or 'video')
     * @param string $url The media URL
     */
    protected function getExtensionFromType(string $mediaType, string $url): string
    {
        // Check URL for hints - handle .lepton.jpeg format
        $urlPath = parse_url($url, PHP_URL_PATH);
        if ($urlPath) {
            // Handle special formats like .lepton.jpeg
            if (str_contains($urlPath, '.jpeg') || str_contains($urlPath, '.jpg')) {
                return 'jpg';
            }
            if (str_contains($urlPath, '.png')) {
                return 'png';
            }
            if (str_contains($urlPath, '.mp4')) {
                return 'mp4';
            }
            if (str_contains($urlPath, '.webm')) {
                return 'webm';
            }
        }

        // Default based on mediaType from API ('image' or 'video')
        if ($mediaType === 'video') {
            return 'mp4';
        }

        return 'jpg';
    }

    /**
     * Download media from URL and persist to storage.
     */
    protected function downloadAndPersist(string $url, string $storagePath): ?string
    {
        $response = Http::timeout(30)->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to download media: HTTP {$response->status()}");
        }

        // Ensure directory exists
        $directory = dirname($storagePath);
        if (!Storage::disk(self::STORAGE_DISK)->exists($directory)) {
            Storage::disk(self::STORAGE_DISK)->makeDirectory($directory);
        }

        // Save to storage
        Storage::disk(self::STORAGE_DISK)->put($storagePath, $response->body());

        return Storage::disk(self::STORAGE_DISK)->url($storagePath);
    }

    /**
     * Generate card data for frontend rich rendering.
     */
    protected function generateCardData(array $vehicleData): array
    {
        $images = [];
        foreach ($vehicleData['media'] as $media) {
            $images[] = [
                'id' => $media['id'],
                'type' => $media['type'],
                'typeDescription' => $media['typeDescription'],
                'timestamp' => $media['timestamp'],
                'url' => $media['localUrl'] ?? $media['originalUrl'],
                'isPersisted' => $media['isPersisted'],
            ];
        }

        return [
            'dashcamMedia' => [
                'vehicleId' => $vehicleData['vehicleId'],
                'vehicleName' => $vehicleData['vehicleName'],
                'totalImages' => count($images),
                'images' => $images,
            ],
        ];
    }
}

