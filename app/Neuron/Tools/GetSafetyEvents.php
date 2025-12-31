<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use App\Models\Vehicle;
use App\Neuron\Tools\Concerns\FlexibleVehicleSearch;
use App\Samsara\Client\SamsaraClient;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class GetSafetyEvents extends Tool
{
    use FlexibleVehicleSearch;
    /**
     * Human-readable descriptions for safety event types.
     * Keys match both 'label' field and 'name' field from behaviorLabels.
     */
    private const EVENT_TYPE_DESCRIPTIONS = [
        // Labels (internal codes)
        'harshAcceleration' => 'Aceleración brusca',
        'harshBraking' => 'Frenado brusco',
        'harshTurn' => 'Giro brusco',
        'crash' => 'Colisión/Choque',
        'speeding' => 'Exceso de velocidad',
        'distraction' => 'Distracción del conductor',
        'genericDistraction' => 'Distracción del conductor',
        'drowsiness' => 'Somnolencia',
        'obstructedCamera' => 'Cámara obstruida',
        'nearCollision' => 'Casi colisión',
        'followingDistance' => 'Distancia de seguimiento insegura',
        'laneViolation' => 'Violación de carril',
        'rollingStop' => 'Parada incompleta',
        'cellPhoneUsage' => 'Uso de celular',
        'seatbeltViolation' => 'Sin cinturón de seguridad',
        'smoking' => 'Fumando',
        'foodDrink' => 'Comiendo/Bebiendo',
        'Acceleration' => 'Aceleración brusca',
        // Names (human readable from API)
        'Inattentive Driving' => 'Conducción distraída',
        'Harsh Acceleration' => 'Aceleración brusca',
        'Harsh Braking' => 'Frenado brusco',
        'Harsh Turn' => 'Giro brusco',
        'Crash' => 'Colisión/Choque',
        'Speeding' => 'Exceso de velocidad',
        'Drowsiness' => 'Somnolencia',
        'Obstructed Camera' => 'Cámara obstruida',
        'Near Collision' => 'Casi colisión',
        'Following Distance' => 'Distancia de seguimiento insegura',
        'Lane Violation' => 'Violación de carril',
        'Rolling Stop' => 'Parada incompleta',
        'Cell Phone Usage' => 'Uso de celular',
        'Seatbelt Violation' => 'Sin cinturón de seguridad',
        'Smoking' => 'Fumando',
        'Eating or Drinking' => 'Comiendo/Bebiendo',
    ];

    /**
     * Severity level descriptions.
     */
    private const SEVERITY_DESCRIPTIONS = [
        'critical' => 'Crítico',
        'high' => 'Alto',
        'medium' => 'Medio',
        'low' => 'Bajo',
    ];

    /**
     * Event state descriptions.
     */
    private const EVENT_STATE_DESCRIPTIONS = [
        'needsReview' => 'Necesita revisión',
        'needsCoaching' => 'Necesita coaching',
        'dismissed' => 'Descartado',
        'coached' => 'Coaching realizado',
    ];

    public function __construct()
    {
        parent::__construct(
            'GetSafetyEvents',
            'Obtener los últimos eventos de seguridad de la flota con toda la información asociada. Incluye eventos como: frenado brusco, aceleración brusca, exceso de velocidad, distracción del conductor, colisiones, uso de celular, etc. Devuelve información detallada del vehículo, conductor, ubicación con dirección, videos de cámara, y estado del evento.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'vehicle_ids',
                type: PropertyType::STRING,
                description: 'IDs de los vehículos a consultar, separados por coma (máximo 5). Ejemplo: "123456789,987654321"',
                required: false,
            ),
            new ToolProperty(
                name: 'vehicle_names',
                type: PropertyType::STRING,
                description: 'Nombres de los vehículos a consultar, separados por coma (máximo 5). Ejemplo: "Camión 1, Unidad 42"',
                required: false,
            ),
            new ToolProperty(
                name: 'hours_back',
                type: PropertyType::INTEGER,
                description: 'Horas hacia atrás desde ahora para buscar eventos. Por defecto es 1 hora. Máximo: 12 horas.',
                required: false,
            ),
            new ToolProperty(
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Número máximo de eventos a retornar. Por defecto es 5. Máximo: 10.',
                required: false,
            ),
            new ToolProperty(
                name: 'event_state',
                type: PropertyType::STRING,
                description: 'Filtrar por estado: "needsReview", "needsCoaching", "dismissed", "coached".',
                required: false,
            ),
        ];
    }

    public function __invoke(
        ?string $vehicle_ids = null,
        ?string $vehicle_names = null,
        int $hours_back = 1,
        int $limit = 5,
        ?string $event_state = null
    ): string {
        try {
            $vehicleIds = [];
            $vehicleNamesMap = [];

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
                $vehicleIds = array_merge($vehicleIds, $ids);
            }

            // Limit vehicles to 5 to avoid context overflow
            $vehicleIds = array_slice(array_unique($vehicleIds), 0, 5);

            // Limit parameters to reasonable range to avoid context overflow
            $hours_back = max(1, min(12, $hours_back));
            $limit = max(1, min(10, $limit));
            $minutesBefore = $hours_back * 60;

            // Parse event states
            $eventStates = [];
            if ($event_state) {
                $eventStates = array_map('trim', explode(',', $event_state));
            }

            // Fetch safety events from API using the stream endpoint
            $client = new SamsaraClient();
            $response = $client->getRecentSafetyEventsStream(
                $vehicleIds,
                $minutesBefore,
                $eventStates,
                $limit
            );

            // Process and format the response
            $formattedEvents = $this->formatEvents($response, $vehicleNamesMap);

            return json_encode($formattedEvents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return json_encode([
                'error' => true,
                'message' => 'Error al obtener eventos de seguridad: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Format the safety events response for better readability.
     */
    protected function formatEvents(array $response, array $vehicleNamesMap): array
    {
        $events = $response['data'] ?? [];
        $meta = $response['meta'] ?? [];

        $result = [
            'total_events' => count($events),
            'search_range_hours' => round(($meta['searchRangeMinutes'] ?? 60) / 60, 1),
            'period' => [
                'start' => $meta['startTime'] ?? null,
                'end' => $meta['endTime'] ?? null,
            ],
            'events' => [],
        ];

        if (empty($events)) {
            $result['message'] = 'No se encontraron eventos de seguridad en el período especificado.';
            return $result;
        }

        // Group events by vehicle (limit to 5 events per vehicle to avoid context overflow)
        $eventsByVehicle = [];
        $maxEventsPerVehicle = 5;
        
        foreach ($events as $event) {
            // Handle asset (vehicle) data from stream endpoint
            $asset = $event['asset'] ?? [];
            $vehicleId = $asset['id'] ?? $event['vehicle']['id'] ?? 'unknown';
            $vehicleName = $asset['name'] ?? $event['vehicle']['name'] ?? $vehicleNamesMap[$vehicleId] ?? 'Sin nombre';
            
            if (!isset($eventsByVehicle[$vehicleId])) {
                $eventsByVehicle[$vehicleId] = [
                    'vehicle_id' => $vehicleId,
                    'vehicle_name' => $vehicleName,
                    'events' => [],
                ];
            }

            // Limit events per vehicle
            if (count($eventsByVehicle[$vehicleId]['events']) >= $maxEventsPerVehicle) {
                continue;
            }

            // Format individual event (simplified)
            $formattedEvent = $this->formatSingleEvent($event);
            $eventsByVehicle[$vehicleId]['events'][] = $formattedEvent;
        }

        // Convert to array
        $result['events'] = array_values($eventsByVehicle);

        // Add summary by event type
        $eventTypeCounts = [];
        foreach ($events as $event) {
            $eventType = $this->getEventType($event);
            $translatedType = self::EVENT_TYPE_DESCRIPTIONS[$eventType] ?? $eventType;
            $eventTypeCounts[$translatedType] = ($eventTypeCounts[$translatedType] ?? 0) + 1;
        }
        $result['summary_by_type'] = $eventTypeCounts;

        // Add summary by state
        $eventStateCounts = [];
        foreach ($events as $event) {
            $state = $event['eventState'] ?? 'unknown';
            $translatedState = self::EVENT_STATE_DESCRIPTIONS[$state] ?? $state;
            $eventStateCounts[$translatedState] = ($eventStateCounts[$translatedState] ?? 0) + 1;
        }
        $result['summary_by_state'] = $eventStateCounts;

        // Generate card data for frontend
        $result['_cardData'] = $this->generateCardData($result);

        return $result;
    }

    /**
     * Format a single safety event with essential data only (reduced for context size).
     */
    protected function formatSingleEvent(array $event): array
    {
        $eventType = $this->getEventType($event);
        
        $formattedEvent = [
            'type_description' => self::EVENT_TYPE_DESCRIPTIONS[$eventType] ?? $eventType,
            'event_state_description' => isset($event['eventState']) 
                ? (self::EVENT_STATE_DESCRIPTIONS[$event['eventState']] ?? $event['eventState']) 
                : null,
            'timestamp' => $event['createdAtTime'] ?? null,
        ];

        // Simplified location (only address and maps link)
        if (isset($event['location'])) {
            $location = $event['location'];
            $address = $location['address'] ?? [];
            $lat = $location['latitude'] ?? null;
            $lng = $location['longitude'] ?? null;

            // Build simplified address
            $addressParts = [];
            if (!empty($address['street'])) {
                $addressParts[] = $address['street'];
            }
            if (!empty($address['city'])) {
                $addressParts[] = $address['city'];
            }
            if (!empty($address['state'])) {
                $addressParts[] = $address['state'];
            }

            $formattedEvent['location'] = [
                'address' => !empty($addressParts) ? implode(', ', $addressParts) : null,
            ];

            if ($lat && $lng) {
                $formattedEvent['location']['maps_link'] = sprintf(
                    'https://www.google.com/maps?q=%s,%s',
                    $lat,
                    $lng
                );
            }
        }

        // Simplified driver info (just name)
        if (isset($event['driver']['name'])) {
            $formattedEvent['driver'] = ['name' => $event['driver']['name']];
        }

        // Only include first media URL if available
        if (isset($event['media'][0]['url'])) {
            $formattedEvent['video_url'] = $event['media'][0]['url'];
        }

        return $formattedEvent;
    }

    /**
     * Get the primary event type from behavior labels.
     */
    protected function getEventType(array $event): string
    {
        // Try behaviorLabels array first (stream endpoint format)
        if (isset($event['behaviorLabels']) && is_array($event['behaviorLabels']) && !empty($event['behaviorLabels'])) {
            $firstLabel = $event['behaviorLabels'][0];
            return $firstLabel['label'] ?? $firstLabel['name'] ?? 'unknown';
        }
        
        // Try behaviorLabel object (legacy format)
        if (isset($event['behaviorLabel'])) {
            return $event['behaviorLabel']['label'] ?? $event['behaviorLabel']['name'] ?? 'unknown';
        }
        
        return $event['type'] ?? 'unknown';
    }

    /**
     * Get human-readable camera type.
     */
    protected function getCameraType(string $input): string
    {
        return match ($input) {
            'dashcamRoadFacing' => 'Cámara frontal (carretera)',
            'dashcamDriverFacing' => 'Cámara interior (conductor)',
            'leftMirrorMount' => 'Espejo izquierdo',
            'rightMirrorMount' => 'Espejo derecho',
            'rearFacing' => 'Cámara trasera',
            default => $input,
        };
    }

    /**
     * Generate card data formatted for frontend rich cards.
     */
    protected function generateCardData(array $result): array
    {
        return [
            'safetyEvents' => [
                'totalEvents' => $result['total_events'],
                'searchRangeHours' => $result['search_range_hours'],
                'periodStart' => $result['period']['start'] ?? null,
                'periodEnd' => $result['period']['end'] ?? null,
                'summaryByType' => $result['summary_by_type'] ?? [],
                'summaryByState' => $result['summary_by_state'] ?? [],
                'events' => $result['events'],
            ],
        ];
    }

    /**
     * Get list of available event types with descriptions.
     */
    public static function getAvailableEventTypes(): array
    {
        return self::EVENT_TYPE_DESCRIPTIONS;
    }

    /**
     * Get list of available event states with descriptions.
     */
    public static function getAvailableEventStates(): array
    {
        return self::EVENT_STATE_DESCRIPTIONS;
    }
}
