<?php

declare(strict_types=1);

namespace App\Neuron\Tools\Concerns;

use App\Models\Vehicle;

/**
 * Trait for flexible vehicle searching.
 * 
 * Provides smart search capabilities that can find vehicles by:
 * - Exact name match
 * - Partial name match (LIKE)
 * - Numeric portions (e.g., "606" finds "T-606", "TR-606")
 * 
 * Also provides suggestion functionality when multiple matches are found.
 */
trait FlexibleVehicleSearch
{
    /**
     * Find a vehicle using flexible matching.
     * Returns exact match if found, or suggestions if multiple matches.
     * 
     * @param string $searchTerm The search term (name or partial number)
     * @return array{exact: bool, vehicle: ?Vehicle, suggestions: array}
     */
    protected function findVehicleFlexible(string $searchTerm): array
    {
        // First try exact match
        $exactMatch = Vehicle::where('name', $searchTerm)->first();
        if ($exactMatch) {
            return ['exact' => true, 'vehicle' => $exactMatch, 'suggestions' => []];
        }

        // Try LIKE match
        $likeMatches = Vehicle::where('name', 'like', '%' . $searchTerm . '%')
            ->orderBy('name')
            ->limit(10)
            ->get();
        
        if ($likeMatches->count() === 1) {
            return ['exact' => true, 'vehicle' => $likeMatches->first(), 'suggestions' => []];
        }

        if ($likeMatches->count() > 1) {
            return [
                'exact' => false, 
                'vehicle' => null, 
                'suggestions' => $likeMatches->map(fn($v) => [
                    'id' => $v->samsara_id,
                    'name' => $v->name,
                ])->toArray(),
            ];
        }

        // If search contains numbers, search by those numbers
        preg_match_all('/\d+/', $searchTerm, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $number) {
                $numberMatches = Vehicle::where('name', 'like', '%' . $number . '%')
                    ->orderBy('name')
                    ->limit(10)
                    ->get();
                
                if ($numberMatches->count() === 1) {
                    return ['exact' => true, 'vehicle' => $numberMatches->first(), 'suggestions' => []];
                }
                
                if ($numberMatches->count() > 1) {
                    return [
                        'exact' => false, 
                        'vehicle' => null, 
                        'suggestions' => $numberMatches->map(fn($v) => [
                            'id' => $v->samsara_id,
                            'name' => $v->name,
                        ])->toArray(),
                    ];
                }
            }
        }

        return ['exact' => false, 'vehicle' => null, 'suggestions' => []];
    }

    /**
     * Resolve vehicle IDs from names using flexible search.
     * Returns resolved IDs and any suggestions for ambiguous searches.
     * 
     * @param string $vehicleNames Comma-separated vehicle names
     * @return array{vehicleIds: array, vehicleNamesMap: array, suggestions: array}
     */
    protected function resolveVehicleNamesFlexible(string $vehicleNames): array
    {
        $vehicleIds = [];
        $vehicleNamesMap = [];
        $suggestions = [];

        $names = array_map('trim', explode(',', $vehicleNames));
        
        foreach ($names as $name) {
            $matchResult = $this->findVehicleFlexible($name);
            
            if ($matchResult['exact'] && $matchResult['vehicle']) {
                $vehicleIds[] = $matchResult['vehicle']->samsara_id;
                $vehicleNamesMap[$matchResult['vehicle']->samsara_id] = $matchResult['vehicle']->name;
            } elseif (!empty($matchResult['suggestions'])) {
                $suggestions[$name] = $matchResult['suggestions'];
            }
        }

        return [
            'vehicleIds' => $vehicleIds,
            'vehicleNamesMap' => $vehicleNamesMap,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Generate a clarification response when suggestions are available.
     * 
     * @param array $suggestions Map of search term => suggestions
     * @return string JSON response asking for clarification
     */
    protected function generateClarificationResponse(array $suggestions): string
    {
        return json_encode([
            'error' => false,
            'needs_clarification' => true,
            'message' => 'Se encontraron varios vehículos que coinciden con tu búsqueda. Por favor especifica cuál deseas:',
            'suggestions' => $suggestions,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

