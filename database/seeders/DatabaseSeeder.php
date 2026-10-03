<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Default settings, chatbot FAQs and (on an empty database only) the same
 * sample data that awas_db.sql shipped with. Settings and FAQs are
 * insert-or-ignore, so re-running this on a live database never
 * overwrites anything an administrator changed.
 *
 * All sample accounts use the password: Password123!
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();

        if (DB::table('users')->count() === 0) {
            $this->seedSampleData();
        }

        $this->seedChatbotFaqs();
    }

    private function seedSettings(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            ['setting_key' => 'barangay_name', 'setting_value' => 'Barangay Adlay', 'description' => 'Name of the barangay for headers/receipts'],
            ['setting_key' => 'minimum_charge', 'setting_value' => '150', 'description' => 'Minimum water charge in PHP, always billed'],
            ['setting_key' => 'minimum_cubic_meters', 'setting_value' => '10', 'description' => 'Cubic meters covered by the minimum charge'],
            ['setting_key' => 'excess_rate_per_cubic_meter', 'setting_value' => '15', 'description' => 'PHP per cubic meter beyond the minimum'],
            ['setting_key' => 'senior_discount_percent', 'setting_value' => '20', 'description' => 'Discount percentage on the sub-total for senior citizens'],
            ['setting_key' => 'due_day_of_month', 'setting_value' => '19', 'description' => 'Day of the month after the billing month on which the bill is due'],
            ['setting_key' => 'disconnection_days', 'setting_value' => '5', 'description' => 'Days after the due date on which service is subject to disconnection'],
            ['setting_key' => 'overdue_grace_days', 'setting_value' => '5', 'description' => 'Days after due_date before a bill is marked overdue and penalty applies'],
            ['setting_key' => 'currency_symbol', 'setting_value' => 'PHP', 'description' => 'Currency label used in reports'],
            ['setting_key' => 'contact_email', 'setting_value' => 'agas.adlay@example.com', 'description' => 'Support email shown to residents'],
            ['setting_key' => 'contact_number', 'setting_value' => '09171234567', 'description' => 'Support contact number'],
            ['setting_key' => 'gcash_qr_file', 'setting_value' => '', 'description' => 'Barangay official GCash QR code image (uploaded in System Settings)'],
            ['setting_key' => 'gcash_account_name', 'setting_value' => '', 'description' => 'Name shown on the barangay GCash account'],
            ['setting_key' => 'gcash_number', 'setting_value' => '', 'description' => 'Barangay GCash mobile number'],
            ['setting_key' => 'chatbot_ai_enabled', 'setting_value' => '0', 'description' => 'Whether the AGAS Assistant chatbot may fall back to the AI API for questions the FAQ knowledge base cannot answer (requires CHATBOT_AI_API_KEY in .env)'],
        ]);
    }

    private function seedSampleData(): void
    {
        foreach (['Purok 1', 'Purok 2', 'Purok 3(Phase 2)', 'Purok 4(Phase 2)', 'Purok 4(Extension)', 'Purok 6', 'Purok 7', 'Purok 8'] as $name) {
            DB::table('puroks')->insertOrIgnore(['purok_name' => $name]);
        }

        $hash = Hash::make('Password123!');
        $users = [
            ['admin', 'Barangay AGAS Administrator', 'admin@agas-adlay.local', '09171000001', 'admin'],
            ['meterreader', 'Juana Dela Cruz', 'meterreader@agas-adlay.local', '09171000002', 'staff'],
            ['resident1', 'Pedro Santos', 'pedro.santos@example.com', '09171000003', 'resident'],
            ['resident2', 'Maria Reyes', 'maria.reyes@example.com', '09171000004', 'resident'],
        ];
        $ids = [];
        foreach ($users as [$username, $name, $email, $contact, $role]) {
            $ids[$username] = DB::table('users')->insertGetId([
                'username' => $username, 'password_hash' => $hash, 'full_name' => $name,
                'email' => $email, 'contact_number' => $contact, 'role' => $role, 'status' => 'active',
            ], 'user_id');
        }
        $admin = $ids['admin'];
        $staff = $ids['meterreader'];
        // Meter Reader 1 reads Purok 1 and Purok 6.
        foreach (DB::table('puroks')->whereIn('purok_name', ['Purok 1', 'Purok 6'])->pluck('purok_id') as $purokId) {
            DB::table('meter_reader_puroks')->insertOrIgnore(['user_id' => $staff, 'purok_id' => $purokId]);
        }

        $rates = [
            ['Minimum Charge (0-10 cu.m.)', 0.00, 10.00, 0.00],
            ['Tier 2 (11-20 cu.m.)', 10.01, 20.00, 18.00],
            ['Tier 3 (21-30 cu.m.)', 20.01, 30.00, 22.00],
            ['Tier 4 (31 cu.m. and above)', 30.01, null, 28.00],
        ];
        foreach ($rates as [$name, $min, $max, $rate]) {
            DB::table('billing_rates')->insert([
                'rate_name' => $name, 'min_consumption' => $min, 'max_consumption' => $max,
                'rate_per_cubic_meter' => $rate, 'base_charge' => 100.00, 'penalty_percentage' => 5.00,
                'is_active' => true, 'effective_date' => '2026-01-01', 'created_by' => $admin,
            ]);
        }

        $purok = fn (string $name) => DB::table('puroks')->where('purok_name', $name)->value('purok_id');
        // Meter Numbers are digits only.
        $consumers = [
            ['1001', $ids['resident1'], 'Pedro Santos', 'Blk 2 Lot 5, Purok 1, Adlay', 'Purok 1', '09171000003', 'pedro.santos@example.com', 'residential', '2024-03-15'],
            ['1002', $ids['resident2'], 'Maria Reyes', 'Purok 3, Riverside St., Adlay', 'Purok 3(Phase 2)', '09171000004', 'maria.reyes@example.com', 'residential', '2024-05-20'],
            ['1003', null, 'Roberto Garcia', 'Purok 2, Adlay', 'Purok 2', '09179998887', null, 'commercial', '2023-11-10'],
            ['1004', null, 'Liza Fernandez', 'Purok 4, Upper Adlay', 'Purok 4(Phase 2)', '09179998886', null, 'residential', '2024-01-05'],
        ];
        $c = [];
        $meters = [];
        foreach ($consumers as $i => [$meter, $uid, $name, $addr, $purokName, $contact, $email, $type, $conn]) {
            $c[$i + 1] = DB::table('consumers')->insertGetId([
                'meter_number' => $meter, 'user_id' => $uid, 'full_name' => $name, 'address' => $addr,
                'purok_id' => $purok($purokName), 'consumer_type' => $type, 'contact_number' => $contact, 'email' => $email,
                'connection_date' => $conn, 'status' => 'active', 'created_by' => $admin,
            ], 'consumer_id');
            $meters[$i + 1] = $meter;
        }

        $readings = [
            [1, '2026-07', 100.00, 112.00, '2026-07-28', 'Normal reading'],
            [1, '2026-08', 112.00, 125.00, '2026-08-28', 'Normal reading'],
            [2, '2026-07', 50.00, 58.00, '2026-07-28', 'Normal reading'],
            [2, '2026-08', 58.00, 70.00, '2026-08-28', 'Normal reading'],
            [3, '2026-08', 200.00, 235.00, '2026-08-28', 'High consumption - check for leaks'],
            [4, '2026-08', 30.00, 33.00, '2026-08-28', 'Normal reading'],
        ];
        $r = [];
        foreach ($readings as $i => [$ci, $period, $prev, $curr, $date, $remarks]) {
            $r[$i + 1] = DB::table('meter_readings')->insertGetId([
                'consumer_id' => $c[$ci], 'billing_period' => $period, 'previous_reading' => $prev,
                'current_reading' => $curr, 'reading_date' => $date, 'recorded_by' => $staff, 'remarks' => $remarks,
            ], 'reading_id');
        }

        // Same amounts computeBillAmount() produces: PHP 150 for the first
        // 10 cu.m. + PHP 15 per cu.m. beyond, due the 19th of the next month.
        $bills = [
            [1, 1, '2026-07', 12.00, 180.00, 180.00, '2026-07-29', '2026-08-19', '2026-08-24', 'paid'],
            [1, 2, '2026-08', 13.00, 195.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid'],
            [2, 3, '2026-07', 8.00, 150.00, 150.00, '2026-07-29', '2026-08-19', '2026-08-24', 'paid'],
            [2, 4, '2026-08', 12.00, 180.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid'],
            [3, 5, '2026-08', 35.00, 525.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid'],
            [4, 6, '2026-08', 3.00, 150.00, 0.00, '2026-08-29', '2026-09-19', '2026-09-24', 'unpaid'],
        ];
        $b = [];
        foreach ($bills as $i => [$ci, $ri, $period, $cons, $total, $paid, $billDate, $due, $disc, $status]) {
            $b[$i + 1] = DB::table('water_bills')->insertGetId([
                'bill_number' => $meters[$ci] . '-' . $period, 'consumer_id' => $c[$ci], 'reading_id' => $r[$ri], 'billing_period' => $period,
                'consumption' => $cons, 'rate_id' => null, 'amount_due' => $total, 'discount_amount' => 0,
                'penalty_amount' => 0, 'total_amount' => $total, 'amount_paid' => $paid, 'bill_date' => $billDate,
                'due_date' => $due, 'disconnection_date' => $disc, 'status' => $status, 'generated_by' => $staff,
            ], 'bill_id');
        }

        DB::table('payments')->insert([
            [
                'payment_reference' => 'PMT-2026-000001', 'bill_id' => $b[1], 'consumer_id' => $c[1], 'amount_paid' => 180.00,
                'payment_method' => 'cash', 'payment_gateway_txn_id' => null, 'payment_date' => '2026-08-01 10:15:00',
                'status' => 'verified', 'received_by' => $staff, 'remarks' => 'Paid over the counter',
            ],
            [
                'payment_reference' => 'PMT-2026-000002', 'bill_id' => $b[3], 'consumer_id' => $c[2], 'amount_paid' => 150.00,
                'payment_method' => 'gcash', 'payment_gateway_txn_id' => 'GC-TXN-88213', 'payment_date' => '2026-08-02 14:30:00',
                'status' => 'verified', 'received_by' => $staff, 'remarks' => 'Paid via GCash',
            ],
        ]);

        DB::table('notifications')->insert([
            ['user_id' => $ids['resident1'], 'bill_id' => $b[2], 'title' => 'New Water Bill Available', 'message' => 'Your water bill for August 2026 (PHP 195.00) is now available. Due on 2026-09-19.', 'type' => 'bill_due', 'is_read' => false],
            ['user_id' => $ids['resident2'], 'bill_id' => $b[4], 'title' => 'New Water Bill Available', 'message' => 'Your water bill for August 2026 (PHP 180.00) is now available. Due on 2026-09-19.', 'type' => 'bill_due', 'is_read' => false],
            ['user_id' => $ids['resident1'], 'bill_id' => $b[1], 'title' => 'Payment Received', 'message' => 'We received your payment of PHP 180.00 for your July 2026 water bill. Thank you!', 'type' => 'payment_received', 'is_read' => true],
        ]);
    }

    private function seedChatbotFaqs(): void
    {
        $creator = DB::table('users')->where('role', 'admin')->orderBy('user_id')->value('user_id');

        $faqs = [
            ['What is my current water bill?', 'You can view your unpaid bills anytime on the "Current Bills" page in your resident dashboard. It shows your consumption, amount due, any penalties, and your remaining balance.', 'billing', 'current bill, my bill, view bill, water bill'],
            ['How is my water bill calculated?', 'Your bill = Base Charge + (billable consumption x rate per cubic meter), based on the tiered rate bracket your consumption falls into. Rates are set by the barangay — see your printed bill for the exact breakdown.', 'billing', 'calculate, computation, formula, tier, rate, bracket'],
            ['What is my water consumption?', 'Your consumption is the difference between your current and previous meter readings for the billing period, measured in cubic meters (m3). Check it anytime on the "My Consumption" page.', 'billing', 'consumption, usage, cubic meter, water used'],
            ['When is my bill due?', 'Your exact due date is shown on your bill and on the "Current Bills" page — it is normally set a number of days after the bill date.', 'billing', 'due date, deadline, when pay'],
            ['What happens if my bill is overdue?', 'If a bill is not paid within the grace period after its due date, it is marked overdue and a penalty percentage (set by the barangay) is added to your total amount due.', 'billing', 'overdue, penalty, late payment, past due'],
            ['Where can I view my billing history?', 'Open "Billing History" in your resident menu to see your past, fully paid water bills. Bills you still need to pay are under "Current Bills".', 'billing', 'billing history, past bills, previous bills'],
            ['Why is my water bill different this month?', 'Bill amounts change with your actual consumption each period, and can also rise if an overdue penalty was added or if billing rates were updated. Compare your readings on the "My Consumption" page.', 'billing', 'bill different, bill changed, why higher, why increased'],
            ['What is a meter reading?', 'A meter reading is the number recorded from your water meter dial by barangay staff each billing period. The difference between two consecutive readings equals your consumption for that period.', 'meter_reading', 'meter reading, what is reading'],
            ['How is my water consumption calculated from meter readings?', 'Consumption = Current Meter Reading - Previous Meter Reading, in cubic meters. This is recorded by our meter reader staff and used to compute your bill.', 'meter_reading', 'consumption calculation, current reading, previous reading'],
            ['How often is the meter read?', 'Meter readings are taken once every billing period, typically monthly, by barangay water staff.', 'meter_reading', 'how often, reading schedule, monthly reading'],
            ['What should I do if I think my meter reading is incorrect?', 'Please contact the Barangay Adlay water office with your meter number and the reading in question. Meter readings can only be corrected by authorized staff, not through this chatbot.', 'meter_reading', 'wrong reading, incorrect reading, dispute reading'],
            ['What should I do if I suspect a water leak?', 'Please report it immediately to the Barangay Adlay water office so staff can inspect your meter and connection. An unusually high consumption on your "My Consumption" page can be a sign of a leak.', 'meter_reading', 'leak, water leak, high consumption, pipe leak'],
            ['How can I pay my water bill?', 'Pay in person at the barangay water office (Cash or GCash QR), or online: open "Current Bills", click "Pay Bill", scan the barangay GCash QR code with your GCash app, pay the exact amount, then click "I Have Paid" and enter your GCash reference number. Your bill shows "Pending Verification" until the water office confirms the payment.', 'payments', 'how to pay, payment methods, gcash, qr, scan, cash, online payment'],
            ['How can I check if my payment was recorded?', 'Open "Payment History" in your resident menu — every payment you have made is listed there along with its status: pending, verified, failed, or refunded.', 'payments', 'check payment, payment recorded, confirm payment'],
            ['Where can I see my payment history?', 'Go to "Payment History" in your resident dashboard menu to see all your submitted and verified payments.', 'payments', 'payment history, past payments'],
            ['Why is my payment still pending?', 'Online GCash payments stay "Pending Verification" until the barangay water office matches your GCash reference number with the money received. Once verified, your bill changes to "Paid". If it is rejected, you will see the reason and can pay again.', 'payments', 'payment pending, still pending, not verified, pending verification'],
            ['What should I do if my payment was not reflected?', 'If it has been more than a few business days and your payment is still not verified or applied to your bill, please contact the Barangay Adlay water office with your payment reference number.', 'payments', 'payment not reflected, missing payment, payment not applied'],
            ['How do I register?', 'Go to the "Create Account" page and enter your meter number (numbers only), full name, and purok exactly as registered with the barangay. If they match an existing consumer record without a linked login, your account will be created.', 'account', 'register, sign up, create account'],
            ['How do I log in?', 'Use the username and password you created during registration on the AGAS Login page.', 'account', 'log in, login, sign in'],
            ['How can I update my account information?', 'Go to "My Profile" in your resident menu to update your email and contact number, or to change your password.', 'account', 'update profile, update information, edit account'],
            ['I forgot my password. What should I do?', 'For security reasons, this chatbot cannot reset passwords. Please contact the Barangay Adlay water office or system administrator, and they can issue you a new temporary password.', 'account', 'forgot password, reset password, lost password'],
            ['How can I view my consumer information?', 'Your meter number, address, purok, type of consumer, and connection status are all shown on the "My Profile" page.', 'account', 'consumer information, my account, account details'],
            ['What is AGAS?', "AGAS (Smart Water Management and Billing System with Online Payment) is Barangay Adlay's digital platform for meter reading, water billing, and payment monitoring.", 'system', 'what is agas, about agas'],
            ['What services does AGAS provide?', 'AGAS lets you view your water bills and consumption, review your billing and payment history, and submit online payments — all without visiting the barangay office in person.', 'system', 'services, features, what can agas do'],
            ['How can I use the AGAS dashboard?', 'Your dashboard shows your latest consumption, current bill balance, due date, payment status, a consumption trend chart, and your recent payments — everything at a glance.', 'system', 'use dashboard, dashboard help'],
            ['Where can I see my current bill?', 'Click "Current Bills" in your resident menu to see your unpaid bill(s) and to submit a payment.', 'system', 'current bill page, see my bill'],
            ['Where can I see my previous bills?', 'Click "Billing History" in your resident menu to see all bills from previous billing periods.', 'system', 'previous bills, past bills, billing history page'],
        ];

        DB::table('chatbot_faqs')->insertOrIgnore(array_map(fn ($f) => [
            'question' => $f[0], 'answer' => $f[1], 'category' => $f[2], 'keywords' => $f[3],
            'status' => 'active', 'created_by' => $creator,
        ], $faqs));
    }
}
