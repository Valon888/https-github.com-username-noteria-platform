<?php
/**
 * COUNTRY CONFIGURATION & PRICING ENGINE
 * Handles multi-country prices, regulations, and settings
 * Created: 2026-03-12
 */

class CountryManager {

    private $db;
    private $current_country = 'XK'; // Default: Kosovo
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /**
     * Get all active countries
     */
    public function getCountries() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, code, name, name_sq, currency, language 
                FROM countries 
                WHERE active = TRUE 
                ORDER BY name_sq
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching countries: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get country by code
     */
    public function getCountry($code) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM countries 
                WHERE code = :code AND active = TRUE
            ");
            $stmt->execute(['code' => $code]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching country: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set current country (for session)
     */
    public function setCountry($code) {
        $country = $this->getCountry($code);
        if ($country) {
            $this->current_country = $code;
            $_SESSION['country_code'] = $code;
            return true;
        }
        return false;
    }

    /**
     * Get current country
     */
    public function getCurrentCountry() {
        if (isset($_SESSION['country_code'])) {
            return $_SESSION['country_code'];
        }
        return $this->current_country;
    }

    /**
     * Get all services for a country
     */
    public function getServicesByCountry($country_code = null) {
        $country_code = $country_code ?? $this->getCurrentCountry();
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    service_name,
                    service_name_sq,
                    base_price,
                    currency,
                    description,
                    min_price,
                    max_price
                FROM country_pricing
                WHERE country_code = :country_code AND active = TRUE
                ORDER BY service_name_sq
            ");
            $stmt->execute(['country_code' => $country_code]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching services: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get price for a service in specific country
     */
    public function getServicePrice($service_name, $country_code = null) {
        $country_code = $country_code ?? $this->getCurrentCountry();
        
        try {
            $stmt = $this->db->prepare("
                SELECT base_price, currency 
                FROM country_pricing
                WHERE service_name = :service AND country_code = :country_code AND active = TRUE
                LIMIT 1
            ");
            $stmt->execute([
                'service' => $service_name,
                'country_code' => $country_code
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['base_price'] : 0;
        } catch (Exception $e) {
            error_log("Error fetching service price: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get selected service details
     */
    public function getServiceDetails($service_name, $country_code = null) {
        $country_code = $country_code ?? $this->getCurrentCountry();
        
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM country_pricing
                WHERE service_name = :service AND country_code = :country_code AND active = TRUE
                LIMIT 1
            ");
            $stmt->execute([
                'service' => $service_name,
                'country_code' => $country_code
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching service details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get regulations for a country
     */
    public function getRegulations($country_code = null) {
        $country_code = $country_code ?? $this->getCurrentCountry();
        
        try {
            $stmt = $this->db->prepare("
                SELECT regulation_type, regulation_name_sq, description
                FROM country_regulations
                WHERE country_code = :country_code AND active = TRUE
                ORDER BY regulation_type
            ");
            $stmt->execute(['country_code' => $country_code]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching regulations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get currency symbol for country
     */
    public function getCurrency($country_code = null) {
        $country_code = $country_code ?? $this->getCurrentCountry();
        $country = $this->getCountry($country_code);
        return $country ? $country['currency'] : 'EUR';
    }

    /**
     * Get pricing list for dropdown (HTML select options)
     */
    public function getPricingOptions($country_code = null) {
        $services = $this->getServicesByCountry($country_code);
        $options = [];
        
        foreach ($services as $service) {
            $options[$service['service_name']] = [
                'label_sq' => $service['service_name_sq'],
                'price' => $service['base_price'],
                'description' => $service['description']
            ];
        }
        
        return $options;
    }

    /**
     * Update pricing for a service
     */
    public function updateServicePrice($service_name, $new_price, $country_code = null) {
        $country_code = $country_code ?? $this->getCurrentCountry();
        
        try {
            $stmt = $this->db->prepare("
                UPDATE country_pricing 
                SET base_price = :price, updated_at = NOW()
                WHERE service_name = :service AND country_code = :country_code
            ");
            
            $result = $stmt->execute([
                'price' => $new_price,
                'service' => $service_name,
                'country_code' => $country_code
            ]);
            
            if ($result) {
                $this->logChange('PRICE_UPDATE', "Updated price for $service_name to $new_price", $country_code);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error updating price: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log changes to country system
     */
    private function logChange($type, $description, $country_code) {
        try {
            $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
            $stmt = $this->db->prepare("
                INSERT INTO country_change_log (country_code, change_type, description, changed_by)
                VALUES (:country_code, :change_type, :description, :changed_by)
            ");
            $stmt->execute([
                'country_code' => $country_code,
                'change_type' => $type,
                'description' => $description,
                'changed_by' => $admin_id
            ]);
        } catch (Exception $e) {
            error_log("Error logging change: " . $e->getMessage());
        }
    }

    /**
     * Get currency symbol
     */
    public static function getCurrencySymbol($code) {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'RSD' => 'дин',
            'XK' => '€'
        ];
        return $symbols[$code] ?? $code;
    }
}

// Initialize global country manager if not exists
if (!isset($GLOBALS['country_manager'])) {
    try {
        $GLOBALS['country_manager'] = new CountryManager($pdo);
    } catch (Exception $e) {
        error_log("Failed to initialize CountryManager: " . $e->getMessage());
    }
}

function getCountryManager() {
    return $GLOBALS['country_manager'] ?? null;
}
