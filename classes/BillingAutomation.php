<?php
/**
 * BillingAutomation Class
 * Menaxhon automatizimin e faturimit, kujtesave, vonesa, zbritje dhe emërime
 */
class BillingAutomation {
    private $pdo;
    private $subscriptionsTable = 'subscription';
    private $invoicesTable = 'invoices';
    private $remindersTable = 'payment_reminders';
    private $delaysTable = 'payment_delays';
    private $plansTable = 'subscription_plans';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Krijo faturë të re për abonimin
     */
    public function generateInvoice($subscriptionId, $planId) {
        $subscription = $this->getSubscription($subscriptionId);
        $plan = $this->getPlan($planId);
        
        if (!$subscription || !$plan) {
            return false;
        }

        // Llogarit shumën
        $amount = $plan['monthly_price'];
        $discountAmount = 0;

        // Zbato zbritje nëse ka
        if ($subscription['discount_id']) {
            $discount = $this->getDiscount($subscription['discount_id']);
            if ($discount) {
                $discountAmount = $this->calculateDiscountAmount($amount, $discount);
                $amount -= $discountAmount;
            }
        }

        // Llogarit tatimin (përqindja standarde 20%)
        $vat = $amount * 0.20;
        $totalAmount = $amount + $vat;

        // Krijo numrin e fatures
        $invoiceNumber = $this->generateInvoiceNumber();

        // Përshkrim
        $description = "Abonimin {$plan['name']} - " . date('F Y');

        // Datat
        $dateIssued = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+30 days'));
        $periodStart = $subscription['created_at'] ?? date('Y-m-d');
        $periodEnd = date('Y-m-d', strtotime('+1 month', strtotime($periodStart)));

        $sql = "INSERT INTO {$this->invoicesTable}
                (invoice_number, zyra_id, amount, vat, total_amount, description,
                 date_issued, due_date, service_period_start, service_period_end,
                 subscription_id, discount_id, discount_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued')";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $invoiceNumber,
            $subscription['zyra_id'],
            $amount,
            $vat,
            $totalAmount,
            $description,
            $dateIssued,
            $dueDate,
            $periodStart,
            $periodEnd,
            $subscriptionId,
            $subscription['discount_id'],
            $discountAmount
        ]);

        if ($result) {
            $invoiceId = $this->pdo->lastInsertId();

            // Përditëso next_billing_date në subscription
            $nextBillingDate = date('Y-m-d H:i:s', strtotime('+' . 
                ($subscription['billing_cycle'] === 'yearly' ? '12' : '1') . ' months'));
            
            $sql = "UPDATE {$this->subscriptionsTable} 
                    SET next_billing_date = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$nextBillingDate, $subscriptionId]);

            // Krijo kujtesa pagese
            $this->createPaymentReminders($invoiceId, $subscriptionId);

            return $invoiceId;
        }

        return false;
    }

    /**
     * Krijo kujtesa pagese automake
     */
    public function createPaymentReminders($invoiceId, $subscriptionId) {
        $invoice = $this->getInvoice($invoiceId);
        if (!$invoice) return false;

        $dueDate = strtotime($invoice['due_date']);
        $now = strtotime(date('Y-m-d'));

        // Kujtes 3 ditë para
        $reminders = [
            [
                'type' => '3days_before',
                'days' => -3,
                'subject' => 'Fatura juaj është në pritje - Paguhet në 3 ditë',
                'template' => 'reminder_3days_before'
            ],
            [
                'type' => '1day_before',
                'days' => -1,
                'subject' => 'Fatura juaj në afat - Paguhet nesër',
                'template' => 'reminder_1day_before'
            ]
        ];

        foreach ($reminders as $reminder) {
            $scheduledDate = date('Y-m-d H:i:s', $dueDate + ($reminder['days'] * 86400));
            
            if (strtotime($scheduledDate) > $now) {
                $sql = "INSERT INTO {$this->remindersTable}
                        (subscription_id, invoice_id, reminder_type, scheduled_date, status)
                        VALUES (?, ?, ?, ?, 'scheduled')";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$subscriptionId, $invoiceId, $reminder['type'], $scheduledDate]);
            }
        }

        return true;
    }

    /**
     * Dërgo kujtesa të planifikuara
     */
    public function sendScheduledReminders() {
        $sql = "SELECT r.*, i.id as invoice_id, i.amount, i.total_amount, i.due_date,
                        z.email, z.name as zyra_name
                FROM {$this->remindersTable} r
                JOIN {$this->invoicesTable} i ON r.invoice_id = i.id
                JOIN zyrat z ON i.zyra_id = z.id
                WHERE r.status = 'scheduled' 
                AND r.scheduled_date <= NOW()
                AND r.scheduled_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $stmt = $this->pdo->query($sql);
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reminders as $reminder) {
            if ($this->sendReminderEmail($reminder)) {
                $sql = "UPDATE {$this->remindersTable} 
                        SET sent_date = NOW(), status = 'sent' WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$reminder['id']]);
            }
        }

        return count($reminders);
    }

    /**
     * Dërgo email kujtes
     */
    private function sendReminderEmail($reminder) {
        // TODO: Implementoni integrimin me PHPMailer
        // Ky është placeholder për logjikën e dërgimit të email-it
        
        $subject = $this->getReminderSubject($reminder['reminder_type']);
        $body = $this->getReminderBody($reminder);
        
        // Dërgo email në $reminder['email']
        // return mail($reminder['email'], $subject, $body, ...);
        
        return true;
    }

    /**
     * Merr temën e kujesës
     */
    private function getReminderSubject($type) {
        $subjects = [
            '3days_before' => 'Kujto: Fatura juaj në pritje - Paguhet në 3 ditë',
            '1day_before' => 'Kujto: Fatura juaj në afat - Paguhet nesër',
            '1day_after' => 'Fatura juaj është vonuar me 1 ditë',
            '7days_after' => 'Shtim i rëndësishëm: Fatura juaj është vonuar me 7 ditë',
            'overdue' => 'Seri! Fatura juaj është seriozisht vonuar'
        ];
        
        return $subjects[$type] ?? 'Përditësim në lidhje me faturën tuaj';
    }

    /**
     * Merr tekstin e kujesës
     */
    private function getReminderBody($reminder) {
        $body = "Përshëndetje {$reminder['zyra_name']},\n\n";
        
        $body .= "Kjo është një kujtes automatik në lidhje me faturën tuaj:\n";
        $body .= "- Shumë: {$reminder['total_amount']} EUR\n";
        $body .= "- Data e afatit: {$reminder['due_date']}\n\n";
        
        $body .= "Ju lutemi, paguani sa më parë në mënyrë që të evitoni penalitete.\n\n";
        $body .= "Faleminderit,\nNoteria Team";
        
        return $body;
    }

    /**
     * Kontrollo vonesa pagese dhe krijo regjistrat
     */
    public function checkOverduePayments() {
        $sql = "SELECT i.*, s.id as subscription_id
                FROM {$this->invoicesTable} i
                JOIN {$this->subscriptionsTable} s ON i.subscription_id = s.id
                WHERE i.status = 'issued'
                AND i.due_date < CURDATE()
                AND i.id NOT IN (SELECT invoice_id FROM {$this->delaysTable})";
        
        $stmt = $this->pdo->query($sql);
        $overdueInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($overdueInvoices as $invoice) {
            $daysOverdue = (strtotime(date('Y-m-d')) - strtotime($invoice['due_date'])) / 86400;
            
            // Llogarit penalitetin (0.5% për ditë von, maksimum 10%)
            $penaltyPercentage = min($daysOverdue * 0.5, 10);
            $penaltyFee = ($invoice['total_amount'] * $penaltyPercentage) / 100;

            $sql = "INSERT INTO {$this->delaysTable}
                    (invoice_id, subscription_id, days_overdue, penalty_fee, first_overdue_date, status)
                    VALUES (?, ?, ?, ?, NOW(), 'active')";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $invoice['id'],
                $invoice['subscription_id'],
                floor($daysOverdue),
                $penaltyFee
            ]);

            // Përditëso faturën me penalitetin
            $sql = "UPDATE {$this->invoicesTable} 
                    SET late_payment_penalty = ? 
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$penaltyFee, $invoice['id']]);
        }

        return count($overdueInvoices);
    }

    /**
     * Dërgo kujtesa për vonesa
     */
    public function sendOverdueReminders() {
        $sql = "SELECT d.*, i.id as invoice_id, i.amount, i.total_amount, i.due_date,
                        z.email, z.name as zyra_name, d.days_overdue
                FROM {$this->delaysTable} d
                JOIN {$this->invoicesTable} i ON d.invoice_id = i.id
                JOIN zyrat z ON i.zyra_id = z.id
                WHERE d.status = 'active'
                AND (d.last_reminder_date IS NULL OR d.last_reminder_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
                ORDER BY d.days_overdue DESC";
        
        $stmt = $this->pdo->query($sql);
        $overdueReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($overdueReminders as $delay) {
            // Përcakto tipin e kujesës bazuar në ditë von
            $reminderType = $delay['days_overdue'] <= 1 ? '1day_after' :
                           ($delay['days_overdue'] <= 7 ? '7days_after' : 'overdue');

            // Dërgo email
            $subject = $this->getReminderSubject($reminderType);
            $body = $this->buildOverdueReminderBody($delay);

            // TODO: Dërgo email
            
            // Përditëso last_reminder_date
            $sql = "UPDATE {$this->delaysTable} 
                    SET last_reminder_date = NOW() 
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$delay['id']]);
        }

        return count($overdueReminders);
    }

    /**
     * Ndërtoni tekstin e kujesës për vonesa
     */
    private function buildOverdueReminderBody($delay) {
        $body = "Përshëndetje {$delay['zyra_name']},\n\n";
        $body .= "Fatura juaj #{$delay['invoice_id']} është vonuar {$delay['days_overdue']} ditë.\n\n";
        $body .= "Detalet:\n";
        $body .= "- Shumë origjinale: {$delay['amount']} EUR\n";
        $body .= "- Penaliteti për vonesa: {$delay['penalty_fee']} EUR\n";
        $body .= "- Shumë totale për pagesë: " . ($delay['total_amount'] + $delay['penalty_fee']) . " EUR\n\n";
        $body .= "Ju lutemi, paguani sa më parë.\n\nFaleminderit,\nNoteria Team";
        
        return $body;
    }

    /**
     * Krijo plan pagese për vonesa
     */
    public function createPaymentPlan($delayId, $installments = 3) {
        $delay = $this->getDelay($delayId);
        if (!$delay) return false;

        $totalAmountDue = $delay['total_amount'] + $delay['penalty_fee'];
        $installmentAmount = $totalAmountDue / $installments;

        $sql = "UPDATE {$this->delaysTable}
                SET payment_plan_amount = ?, payment_plan_installments = ?
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$installmentAmount, $installments, $delayId]);
    }

    /**
     * Merr abonimin
     */
    private function getSubscription($id) {
        $sql = "SELECT * FROM {$this->subscriptionsTable} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Merr planin
     */
    private function getPlan($id) {
        $sql = "SELECT * FROM {$this->plansTable} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Merr zbritjen
     */
    private function getDiscount($id) {
        $sql = "SELECT * FROM discounts WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Merr faturën
     */
    private function getInvoice($id) {
        $sql = "SELECT * FROM {$this->invoicesTable} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Merr vonesen
     */
    private function getDelay($id) {
        $sql = "SELECT * FROM {$this->delaysTable} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Llogarit shumën e zbritjes
     */
    private function calculateDiscountAmount($amount, $discount) {
        if ($discount['discount_type'] === 'percentage') {
            return ($amount * $discount['discount_value']) / 100;
        } else {
            return min($discount['discount_value'], $amount);
        }
    }

    /**
     * Gjenero numrin e fatures
     */
    private function generateInvoiceNumber() {
        $year = date('Y');
        $month = date('m');
        
        $sql = "SELECT COUNT(*) + 1 as next_number 
                FROM {$this->invoicesTable} 
                WHERE YEAR(date_issued) = ? AND MONTH(date_issued) = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $number = str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
        return "INV-{$year}-{$month}-{$number}";
    }

    /**
     * Kontrollo abonimet e afërta të skadimit
     */
    public function checkExpiringSubscriptions($daysBeforeExpiry = 14) {
        $expiryDate = date('Y-m-d', strtotime("+{$daysBeforeExpiry} days"));
        
        $sql = "SELECT s.*, z.email, z.name as zyra_name, p.name as plan_name
                FROM {$this->subscriptionsTable} s
                JOIN zyrat z ON s.zyra_id = z.id
                JOIN {$this->plansTable} p ON s.plan_id = p.id
                WHERE s.expiry_date = ? 
                AND s.status = 'active'
                AND s.id NOT IN (
                    SELECT DISTINCT subscription_id FROM {$this->remindersTable}
                    WHERE reminder_type = 'expiry_warning'
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$expiryDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Përditëso statusin e abonimeve të skaduar
     */
    public function updateExpiredSubscriptions() {
        $sql = "UPDATE {$this->subscriptionsTable}
                SET status = 'expired'
                WHERE expiry_date < CURDATE()
                AND status = 'active'";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute();
    }
}
