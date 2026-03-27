<?php
/**
 * SubscriptionPlan Class
 * Menaxhon pakete abonimesh të predefinuara
 */
class SubscriptionPlan {
    private $pdo;
    private $table = 'subscription_plans';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Krijo paketë të re abonimesh
     */
    public function createPlan($data) {
        $sql = "INSERT INTO {$this->table} 
                (name, code, description, price_monthly, price_yearly, features, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $this->pdo->prepare($sql);
        
        $features = isset($data['features']) ? json_encode($data['features']) : json_encode([]);
        
        // Gjenero kodin nga slug nëse nuk ofrohet
        $code = $data['code'] ?? $data['slug'] ?? strtolower(str_replace(' ', '-', $data['name']));
        
        return $stmt->execute([
            $data['name'],
            $code,
            $data['description'] ?? null,
            $data['monthly_price'] ?? $data['price_monthly'] ?? 0,
            $data['yearly_price'] ?? $data['price_yearly'] ?? null,
            $features
        ]);
    }

    /**
     * Merr të gjitha pakete aktive
     */
    public function getActivePlans() {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY price_monthly ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Merr paketë sipas ID
     */
    public function getPlanById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($plan && $plan['features']) {
            $plan['features'] = json_decode($plan['features'], true);
        }
        
        return $plan;
    }

    /**
     * Përditëso paketë
     */
    public function updatePlan($id, $data) {
        $updates = [];
        $values = [];
        
        $allowedFields = ['name', 'code', 'description', 'price_monthly', 'price_yearly', 'features', 'is_active'];
        
        foreach ($data as $key => $value) {
            // Konverto emrat e vjetër në të rejë
            if ($key === 'monthly_price') $key = 'price_monthly';
            if ($key === 'yearly_price') $key = 'price_yearly';
            
            if (in_array($key, $allowedFields)) {
                $updates[] = "{$key} = ?";
                if ($key === 'features') {
                    $values[] = json_encode($value);
                } else {
                    $values[] = $value;
                }
            }
        }
        
        if (empty($updates)) return false;
        
        $values[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Fshij paketë
     */
    public function deletePlan($id) {
        $sql = "UPDATE {$this->table} SET is_active = FALSE WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Krijo pakete standarde
     */
    public function createDefaultPlans() {
        $defaultPlans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfekt për fillim të vogël',
                'monthly_price' => 9.99,
                'yearly_price' => 99.99,
                'setup_fee' => 0,
                'features' => ['Në deri 5 dokumente', 'Email support', '1 përdorues'],
                'max_documents' => 5,
                'max_signatures' => 5,
                'max_consultations' => 0,
                'support_level' => 'email',
                'trial_days' => 14
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Për përdorësit profesionalë',
                'monthly_price' => 29.99,
                'yearly_price' => 299.99,
                'setup_fee' => 0,
                'features' => ['Dokumente të pakufizuara', 'Priority email support', 'Deri 5 përdorues', 'Analytics'],
                'max_documents' => -1,
                'max_signatures' => -1,
                'max_consultations' => 5,
                'support_level' => 'priority',
                'trial_days' => 30
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Për biznese të mëdha',
                'monthly_price' => 99.99,
                'yearly_price' => 999.99,
                'setup_fee' => 50,
                'features' => ['Të gjitha featuret', '24/7 Dedicated support', 'Kolegje të pakufizuar', 'API access', 'White label'],
                'max_documents' => -1,
                'max_signatures' => -1,
                'max_consultations' => -1,
                'support_level' => 'dedicated',
                'trial_days' => 30
            ]
        ];

        foreach ($defaultPlans as $plan) {
            $this->createPlan($plan);
        }

        return true;
    }

    /**
     * Merr featuret e planit
     */
    public function getPlanFeatures($planId) {
        $plan = $this->getPlanById($planId);
        return $plan ? $plan['features'] : [];
    }

    /**
     * Kontrolloni nëse plani e ka feature-in
     */
    public function planHasFeature($planId, $feature) {
        $features = $this->getPlanFeatures($planId);
        return in_array($feature, $features);
    }
}
