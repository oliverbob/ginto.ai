<?php
namespace App\Controllers;

use Core\Controller; // IMPORTANT: This gives you access to the database

class LocationController extends Controller
{
    /**
     * Searches for locations in the local GeoNames database based on user input.
     */
    public function search()
    {
        // Set the content type to JSON for all responses
        header('Content-Type: application/json');

        // Check if the database connection is available
        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database service is not available.']);
            exit;
        }

        // Get the search text from the POST request body
        $input = json_decode(file_get_contents('php://input'), true);
        $searchText = trim($input['text'] ?? '');

        if (strlen($searchText) < 2) {
            // Return an empty array for short search terms, which is not an error
            echo json_encode(['success' => true, 'locations' => []]);
            exit;
        }

        // Use a "LIKE" query. The '%' is a wildcard for any characters.
        $searchTerm = $searchText . '%';

        try {
            // This query finds cities, towns, etc., and orders them by population
            // so that major cities (like "Manila") appear before smaller places.
            $locations = $this->db->select('geonames_locations', [
                'geonameid',
                'name',
                'admin1_code' // You can add other fields like country_code if needed
            ], [
                "name[~]" => $searchTerm, // Medoo's syntax for LIKE
                // This filters for populated places (cities, towns, villages)
                "feature_code" => ['PPLC', 'PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'PPL'],
                "ORDER" => ["population" => "DESC"],
                "LIMIT" => 10
            ]);

            if ($locations === false) {
                // This indicates a query failure, not just empty results
                throw new \Exception($this->db->error()[2] ?? 'Database query failed.');
            }

            // Successfully return the found locations (or an empty array if none found)
            echo json_encode(['success' => true, 'locations' => $locations]);
            exit;

        } catch (\Exception $e) {
            // Log the actual error for your records
            error_log("Location search failed: " . $e->getMessage());

            // Send a generic error message to the user
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'A server error occurred during the location search.']);
            exit;
        }
    }
}