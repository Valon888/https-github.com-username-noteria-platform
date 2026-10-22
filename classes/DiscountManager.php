<?php
/**
 * DiscountManager Class
 * Menaxhon zbritje dhe promocione
 */
class DiscountManager {
    private $pdo;
    private $discounts_table = 'discounts';
    private $usage_table = 'discount_usage';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Krijo zbritje të re
     */
    public function createDiscount($data) {
        $sql = "INSERT INTO {$this->discounts_table}
                (code, name, description, discount_type, discount_value, max_uses,
                 valid_from, valid_until, applies_to, applicable_plans, 
                 min_subscription_months, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        
        $applicablePlans = isset($data['applicable_plans']) ? 
                          json_encode($data['applicable_plans']) : json_encode([]);
        
        return $stmt->execute([
            strtoupper($data['code']),
            $data['name'],
            $data['description'] ?? null,
            $data['discount_type'] ?? 'percentage',
            $data['discount_value'],
            $data['max_uses'] ?? -1,
            $data['valid_from'],
            $data['valid_until'],
            $data['applies_to'] ?? 'all_plans',
            $applicablePlans,
            $data['min_subscription_months'] ?? 1,
            true
        ]);
    }

    /**
     * Validimi i kodit të zbritjes
     */
    public function validateDiscount($code, $planId = null, $months = 1) {
        $sql = "SELECT * FROM {$this->discounts_table} 
                WHERE code = ? AND is_active = TRUE 
                AND valid_from <= NOW() AND valid_until >= NOW()";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([strtoupper($code)]);
        $discount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$discount) {
            return ['valid' => false, 'error' => 'Kodi i zbritjes nuk u gjet ose nuk është aktiv'];
        }

        // Kontrolloni nëse ka arritur limitin e përdorimeve
        if ($discount['max_uses'] > 0 && $discount['used_count'] >= $discount['max_uses']) {
            return ['valid' => false, 'error' => 'Ky kod zbritjeje ka arritur limitin e përdorimeve'];
        }

        // Kontrolloni aplikability në plane specifike
        if ($discount['applies_to'] === 'specific_plans' && $planId) {
            $applicablePlans = json_decode($discount['applicable_plans'], true);
            if (!in_array($planId, $applicablePlans)) {
                return ['valid' => false, 'error' => 'Ky kod zbritjeje nuk zbatohet në këtë plan'];
            }
        }

        // Kontrolloni minimumin e muajve të abonimit
        if ($months < $discount['min_subscription_months']) {
            return ['valid' => false, 'error' => 'Ky kod zbritjeje kërkon minimum ' . 
                    $discount['min_subscription_months'] . ' muaj abonimesh'];
        }

        return ['valid' => true, 'discount' => $discount];
    }

    /**
     * Llogarit shumën e zbritjes
     */
    public function calculateDiscountAmount($originalAmount, $discount) {
        if ($discount['discount_type'] === 'percentage') {
            return ($originalAmount * $discount['discount_value']) / 100;
        } else {
            // Fixed amount
            return min($discount['discount_value'], $originalAmount);
        }
    }

    /**
     * Zbatime zbritje në abonimin
     */
    public function applyDiscountToSubscription($discountId, $subscriptionId, $zyraId) {
        $discount = $this->getDiscountById($discountId);
        if (!$discount) return false;

        // Përditëso subscription me zbritjen
        $sql = "UPDATE subscription SET discount_id = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$discountId, $subscriptionId]);

        // Zgjidh përdorimin e zbritjes
        $sql = "INSERT INTO {$this->usage_table}
                (discount_id, subscription_id, zyra_id, discount_amount, applied_date)
                VALUES (?, ?, ?, 0, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$discountId, $subscriptionId, $zyraId]);

        // Përditëso used_count
        $sql = "UPDATE {$this->discounts_table} SET used_count = used_count + 1 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$discountId]);
    }

    /**
     * Merr zbritje sipas ID
     */
    public function getDiscountById($id) {
        $sql = "SELECT * FROM {$this->discounts_table} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Merr të gjitha zbritjet aktive
     */
    public function getActiveDiscounts() {
        $sql = "SELECT * FROM {$this->discounts_table} 
                WHERE is_active = TRUE 
                AND valid_from <= NOW() 
                AND valid_until >= NOW()
                ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Përditëso zbritje
     */
    public function updateDiscount($id, $data) {
        $updates = [];
        $values = [];
        
        $allowedFields = ['name', 'description', 'discount_type', 'discount_value', 
                         'max_uses', 'valid_from', 'valid_until', 'is_active',
                         'applies_to', 'applicable_plans', 'min_subscription_months'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "{$key} = ?";
                if ($key === 'applicable_plans') {
                    $values[] = json_encode($value);
                } else {
                    $values[] = $value;
                }
            }
        }
        
        if (empty($updates)) return false;
        
        $values[] = $id;
        $sql = "UPDATE {$this->discounts_table} SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($values);
    }

    /**
     * Fshij zbritje
     */
    public function deleteDiscount($id) {
        $sql = "UPDATE {$this->discounts_table} SET is_active = FALSE WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
}
