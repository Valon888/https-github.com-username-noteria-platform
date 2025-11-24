<?php
/**
 * Pricing Helper Functions for Advertising System
 * Handles pricing display, subscription calculation, and invoice generation
 */

function getPricingPlans($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pricing_plans WHERE active = 1 ORDER BY price ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting pricing plans: " . $e->getMessage());
        return [];
    }
}

function getPricingPlanById($pdo, $plan_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pricing_plans WHERE id = ? AND active = 1");
        $stmt->execute([$plan_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting pricing plan: " . $e->getMessage());
        return null;
    }
}

function getSubscriptionStatus($pdo, $advertiser_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ap.*, pp.name as plan_name, pp.price as monthly_price
            FROM ad_payments ap
            LEFT JOIN pricing_plans pp ON pp.id = ap.plan_id
            WHERE ap.advertiser_id = ?
            ORDER BY ap.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$advertiser_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting subscription status: " . $e->getMessage());
        return null;
    }
}

function isSubscriptionActive($pdo, $advertiser_id) {
    $subscription = getSubscriptionStatus($pdo, $advertiser_id);
    if (!$subscription) {
        return false;
    }
    
    $now = new DateTime();
    $end_date = new DateTime($subscription['period_end']);
    
    return ($subscription['status'] === 'paid' && $now < $end_date);
}

function createSubscription($pdo, $advertiser_id, $plan_id = 2) {
    try {
        $plan = getPricingPlanById($pdo, $plan_id);
        if (!$plan) {
            throw new Exception("Plan not found");
        }
        
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
        $next_payment_date = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        $stmt = $pdo->prepare("
            INSERT INTO ad_payments (advertiser_id, plan_id, amount, status, period_start, period_end, next_payment_date)
            VALUES (?, ?, ?, 'pending', ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $advertiser_id,
            $plan_id,
            $plan['price'],
            $start_date,
            $end_date,
            $next_payment_date
        ]);
        
        return $result ? $pdo->lastInsertId() : null;
    } catch (Exception $e) {
        error_log("Error creating subscription: " . $e->getMessage());
        return null;
    }
}

function markSubscriptionAsPaid($pdo, $payment_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE ad_payments 
            SET status = 'paid', paid_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$payment_id]);
    } catch (Exception $e) {
        error_log("Error marking subscription as paid: " . $e->getMessage());
        return false;
    }
}

function generateInvoice($pdo, $advertiser_id) {
    try {
        $subscription = getSubscriptionStatus($pdo, $advertiser_id);
        if (!$subscription) {
            return null;
        }
        
        $advertiser = $pdo->prepare("SELECT * FROM advertisers WHERE id = ?");
        $advertiser->execute([$advertiser_id]);
        $advertiser_data = $advertiser->fetch(PDO::FETCH_ASSOC);
        
        if (!$advertiser_data) {
            return null;
        }
        
        $invoice = [
            'invoice_number' => 'INV-' . $advertiser_id . '-' . date('YmD'),
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'advertiser' => $advertiser_data,
            'plan_name' => $subscription['plan_name'],
            'amount' => $subscription['monthly_price'],
            'status' => $subscription['status'],
            'period_start' => $subscription['period_start'],
            'period_end' => $subscription['period_end']
        ];
        
        return $invoice;
    } catch (Exception $e) {
        error_log("Error generating invoice: " . $e->getMessage());
        return null;
    }
}

function getPricingTableHTML($currency = '€') {
    global $pdo;
    
    $plans = getPricingPlans($pdo);
    $html = '<div class="pricing-table"><div class="pricing-grid">';
    
    foreach ($plans as $plan) {
        $is_recommended = ($plan['price'] == 300) ? 'recommended' : '';
        $html .= '<div class="pricing-card ' . $is_recommended . '">';
        
        if ($plan['price'] == 300) {
            $html .= '<div class="recommended-badge">REKOMANDUAR</div>';
        }
        
        $html .= '<h3>' . htmlspecialchars($plan['name']) . '</h3>';
        $html .= '<p class="description">' . htmlspecialchars($plan['description']) . '</p>';
        $html .= '<div class="price"><span class="currency">' . $currency . '</span>';
        $html .= '<span class="amount">' . number_format($plan['price'], 0) . '</span>';
        $html .= '<span class="period">/muaj</span></div>';
        
        if ($plan['features']) {
            $html .= '<ul class="features">';
            foreach (explode(',', $plan['features']) as $feature) {
                $html .= '<li>' . htmlspecialchars(trim($feature)) . '</li>';
            }
            $html .= '</ul>';
        }
        
        $html .= '<button class="select-plan-btn" data-plan-id="' . $plan['id'] . '">Zgjidh këtë plan</button>';
        $html .= '</div>';
    }
    
    $html .= '</div></div>';
    return $html;
}

function getPricingCSS() {
    return '<style>.pricing-table{padding:40px 20px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:10px;margin:30px 0}.pricing-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:30px;max-width:1200px;margin:0 auto}.pricing-card{background:#fff;border-radius:10px;padding:30px;position:relative;transition:all .3s;box-shadow:0 4px 6px rgba(0,0,0,.1)}.pricing-card:hover{transform:translateY(-5px);box-shadow:0 12px 20px rgba(0,0,0,.2)}.pricing-card.recommended{border:3px solid #667eea;transform:scale(1.05)}.pricing-card.recommended:hover{transform:translateY(-5px) scale(1.05)}.recommended-badge{position:absolute;top:-15px;right:20px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:5px 15px;border-radius:20px;font-size:12px;font-weight:bold}.pricing-card h3{margin-top:0;color:#333;font-size:24px}.pricing-card .description{color:#666;font-size:14px;margin:10px 0 20px}.price{display:flex;align-items:baseline;margin:20px 0;gap:5px}.price .currency{font-size:20px;color:#667eea;font-weight:bold}.price .amount{font-size:48px;font-weight:bold;color:#333}.price .period{color:#999;font-size:14px}.pricing-card .features{list-style:none;padding:0;margin:20px 0}.pricing-card .features li{padding:10px 0;color:#555;border-bottom:1px solid #eee;font-size:14px}.pricing-card .features li:before{content:"✓ ";color:#667eea;font-weight:bold;margin-right:8px}.select-plan-btn{width:100%;padding:12px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:5px;font-size:14px;font-weight:bold;cursor:pointer;margin-top:20px;transition:opacity .3s}.select-plan-btn:hover{opacity:.9}</style>';
}

function getPricingJS() {
    return '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".select-plan-btn").forEach(btn=>{btn.addEventListener("click",function(){const form=document.createElement("form");form.method="POST";form.action="select_plan.php";const input=document.createElement("input");input.type="hidden";input.name="plan_id";input.value=this.getAttribute("data-plan-id");form.appendChild(input);document.body.appendChild(form);form.submit()})})});</script>';
}
