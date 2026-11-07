<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MapLookupController extends Controller
{
    public function geocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $location = Location::query()->find($data['location_id'] ?? null);
        $query = $data['q'];

        if ($location) {
            $query .= ', '.$location->name.', '.$location->province.', Philippines';
        }

        $match = filled(config('services.google.maps_key'))
            ? $this->googleGeocode($query, $location)
            : $this->nominatimGeocode($query, $location);

        if ($match === null) {
            return response()->json(['message' => 'That address could not be found.'], 422);
        }

        return response()->json($match);
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $match = filled(config('services.google.maps_key'))
            ? $this->googleReverse((float) $data['latitude'], (float) $data['longitude'])
            : $this->nominatimReverse((float) $data['latitude'], (float) $data['longitude']);

        if ($match === null) {
            return response()->json(['message' => 'No address was found for that pin.'], 422);
        }

        return response()->json($match);
    }

    /**
     * @return array{address: string, latitude: string, longitude: string}|null
     */
    private function googleGeocode(string $query, ?Location $location): ?array
    {
        $params = [
            'address' => $query,
            'components' => 'country:PH',
            'key' => config('services.google.maps_key'),
        ];

        if ($location?->map_latitude !== null && $location->map_longitude !== null) {
            $lat = (float) $location->map_latitude;
            $lng = (float) $location->map_longitude;
            $params['bounds'] = ($lat - 0.2).','.($lng - 0.2).'|'.($lat + 0.2).','.($lng + 0.2);
        }

        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', $params);
        $result = $response->json('results.0');

        if (! is_array($result) || ! isset($result['geometry']['location']['lat'], $result['formatted_address'])) {
            return null;
        }

        return $this->match(
            (string) $result['formatted_address'],
            $result['geometry']['location']['lat'],
            $result['geometry']['location']['lng'],
        );
    }

    /**
     * @return array{address: string, latitude: string, longitude: string}|null
     */
    private function googleReverse(float $latitude, float $longitude): ?array
    {
        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $latitude.','.$longitude,
            'key' => config('services.google.maps_key'),
        ]);
        $result = $response->json('results.0');

        if (! is_array($result) || ! isset($result['formatted_address'])) {
            return null;
        }

        return $this->match((string) $result['formatted_address'], $latitude, $longitude);
    }

    /**
     * @return array{address: string, latitude: string, longitude: string}|null
     */
    private function nominatimGeocode(string $query, ?Location $location): ?array
    {
        $params = [
            'format' => 'jsonv2',
            'limit' => 1,
            'countrycodes' => 'ph',
            'q' => $query,
        ];

        if ($location?->map_latitude !== null && $location->map_longitude !== null) {
            $lat = (float) $location->map_latitude;
            $lng = (float) $location->map_longitude;
            $params['viewbox'] = ($lng - 0.25).','.($lat + 0.25).','.($lng + 0.25).','.($lat - 0.25);
        }

        $response = $this->nominatim('https://nominatim.openstreetmap.org/search', $params);
        $result = $response->json(0);

        if (! is_array($result) || ! isset($result['lat'], $result['lon'], $result['display_name'])) {
            return null;
        }

        return $this->match((string) $result['display_name'], $result['lat'], $result['lon']);
    }

    /**
     * @return array{address: string, latitude: string, longitude: string}|null
     */
    private function nominatimReverse(float $latitude, float $longitude): ?array
    {
        $response = $this->nominatim('https://nominatim.openstreetmap.org/reverse', [
            'format' => 'jsonv2',
            'lat' => $latitude,
            'lon' => $longitude,
        ]);
        $name = $response->json('display_name');

        if (! is_string($name) || $name === '') {
            return null;
        }

        return $this->match($name, $latitude, $longitude);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function nominatim(string $url, array $params): \Illuminate\Http\Client\Response
    {
        return Http::timeout(8)
            ->withHeaders([
                'User-Agent' => config('brand.name').' booking map',
                'Accept' => 'application/json',
            ])
            ->get($url, $params);
    }

    /**
     * @return array{address: string, latitude: string, longitude: string}
     */
    private function match(string $address, float|string $latitude, float|string $longitude): array
    {
        return [
            'address' => Str::limit(trim($address), 255, ''),
            'latitude' => $this->coordinate($latitude),
            'longitude' => $this->coordinate($longitude),
        ];
    }

    private function coordinate(float|string $value): string
    {
        return number_format((float) $value, 7, '.', '');
    }
}
