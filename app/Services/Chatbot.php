<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "AGAS Assistant" resident chatbot: intent detection, FAQ search,
 * personalized account lookups and an optional AI fallback.
 *
 * - READ-ONLY against bills, readings, payments and consumers.
 * - Personalized answers use a consumer row the controller derives from the
 *   logged-in session — nothing here accepts an account id from chat text.
 * - The AI fallback never receives resident-specific data, only the
 *   question and generic FAQ excerpts.
 */
class Chatbot
{
    public function __construct(private Settings $settings)
    {
    }

    /** @return array{reply: string} */
    public function respond(string $message, ?object $consumer, ?int $userId): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['reply' => "Please type a question and I'll do my best to help!"];
        }
        $message = mb_substr($message, 0, 500);

        if ($this->isRestrictedAction($message)) {
            return ['reply' => "I'm here to provide information only — I can't verify payments, edit billing records, or change meter readings. Please visit or call the Barangay Adlay water office for that."];
        }

        $intent = $this->detectIntent($message);
        if ($intent) {
            if (!$consumer) {
                return ['reply' => "I couldn't find a water account linked to your login, so I can't pull up personal details. Please contact the Barangay Adlay water office to get your account linked."];
            }
            $reply = $this->personalAnswer($intent, $consumer);
            if ($reply !== '') return ['reply' => $reply];
        }

        $faq = $this->faqSearch($message);
        if ($faq) {
            DB::table('chatbot_faqs')->where('id', $faq->id)->increment('hit_count');
            return ['reply' => $faq->answer];
        }

        // Ground the optional AI fallback with the site's own FAQ content.
        $excerpts = DB::table('chatbot_faqs')->where('status', 'active')->orderByDesc('hit_count')->limit(6)->get(['question', 'answer']);
        $aiReply = $this->aiFallback($message, $excerpts->all());
        if ($aiReply) {
            return ['reply' => $aiReply];
        }

        DB::table('chatbot_unanswered')->insert(['question' => mb_substr($message, 0, 500), 'asked_by' => $userId]);
        return ['reply' => "I'm sorry, I don't have enough information to answer that question. Please contact the Barangay Adlay water staff or administrator for assistance."];
    }

    /** Phrasing that asks for an action the assistant must never take. */
    private function isRestrictedAction(string $message): bool
    {
        $patterns = [
            '/\b(verify|approve|confirm)\s+(my|the|this)?\s*payment\b/i',
            '/\bmark\s+(my|the)?\s*(bill|payment)\s+as\s+paid\b/i',
            '/\b(change|edit|update|correct|fix|modify)\s+(my\s+)?(meter\s+)?reading\b/i',
            '/\b(change|edit|update|delete|remove|cancel|waive)\s+(my\s+)?(bill|penalty|charge)\b/i',
            '/\b(tell|give|show)\s+me\s+(my|the)?\s*password\b/i',
            '/\bwhat(\'s| is) my password\b/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $message)) return true;
        }
        return false;
    }

    /** Rule-based intent detection for account-specific questions; null = general question. */
    private function detectIntent(string $message): ?string
    {
        $m = strtolower($message);

        if (preg_match('/\bpayment\b.{0,20}\b(status|pending|verified|recorded|reflect)\b/', $m)
            || preg_match('/\b(status|pending|recorded|reflect)\b.{0,20}\bpayment\b/', $m)) {
            return 'payment_status';
        }
        if (preg_match('/\bpayment history\b|\bmy payments\b|\bpast payments\b|\bprevious payments\b/', $m)) {
            return 'payment_history';
        }
        if (preg_match('/\bbilling history\b|\bprevious bills?\b|\bpast bills?\b|\bprior bills?\b/', $m)) {
            return 'billing_history';
        }
        if (!str_contains($m, 'overdue') && preg_match('/\bdue date\b|\bwhen.*(due|pay)\b/', $m)) {
            return 'due_date';
        }
        if (!str_contains($m, 'calculat') && preg_match('/\b(consumption|water used|how much water|usage|cubic meter|m3)\b/', $m)) {
            return 'consumption';
        }
        if (!str_contains($m, 'calculat') && !str_contains($m, 'history')
            && preg_match('/\b(current bill|my bill|how much.*(owe|bill)|my balance|amount due|total amount|how much is my)\b/', $m)) {
            return 'current_bill';
        }
        if (preg_match('/\bmeter number\b|\baccount number\b|\bmy account\b|\bconsumer info\b|\bmy information\b|\baccount information\b|\bmy purok\b/', $m)) {
            return 'account_info';
        }
        return null;
    }

    private function personalAnswer(string $intent, object $consumer): string
    {
        $cid = $consumer->consumer_id;

        switch ($intent) {
            case 'current_bill':
                $bill = DB::table('water_bills')->where('consumer_id', $cid)->where('status', '!=', 'paid')->orderBy('due_date')->first();
                if (!$bill) return "🎉 Good news — you have no outstanding water bill right now. All your bills are paid up to date.";
                $balance = (float)$bill->total_amount - (float)$bill->amount_paid;
                $msg = 'Your current bill for ' . billingPeriodLabel($bill->billing_period)
                    . ' totals ' . formatCurrency($bill->total_amount) . ', with a remaining balance of ' . formatCurrency($balance)
                    . ', due on ' . formatDate($bill->due_date) . '.';
                if ((float)$bill->penalty_amount > 0) {
                    $msg .= ' This includes a ' . formatCurrency($bill->penalty_amount) . ' overdue penalty.';
                }
                return $msg;

            case 'consumption':
                $r = DB::table('meter_readings')->where('consumer_id', $cid)->orderByDesc('billing_period')->first();
                if (!$r) return "We don't have any meter readings on file for your account yet.";
                return 'Your recorded consumption for ' . billingPeriodLabel($r->billing_period) . ' was ' . number_format((float)$r->consumption, 2)
                    . ' m³ (reading went from ' . number_format((float)$r->previous_reading, 2) . ' to ' . number_format((float)$r->current_reading, 2) . ').';

            case 'due_date':
                $bill = DB::table('water_bills')->where('consumer_id', $cid)->where('status', '!=', 'paid')->orderBy('due_date')->first();
                if (!$bill) return "You have no pending bill, so there's no due date to worry about right now. 🎉";
                return 'Your ' . billingPeriodLabel($bill->billing_period) . ' bill is due on ' . formatDate($bill->due_date) . '. Pay before then to avoid an overdue penalty.';

            case 'payment_status':
                $p = DB::table('payments')->where('consumer_id', $cid)->orderByDesc('payment_date')->first();
                if (!$p) return "You don't have any payment records yet.";
                $statusMsg = [
                    'pending'  => 'Pending Verification by the barangay water office',
                    'verified' => 'verified and applied to your bill',
                    'failed'   => 'rejected — please contact the water office',
                    'rejected' => 'rejected' . ($p->rejection_reason ? " (reason: {$p->rejection_reason})" : '') . ' — you can pay again from "Current Bills"',
                    'refunded' => 'refunded',
                ][$p->status] ?? $p->status;
                return "Your most recent payment ({$p->payment_reference}, " . formatCurrency($p->amount_paid) . ") is $statusMsg"
                    . '. Submitted on ' . formatDateTime($p->payment_date) . '.';

            case 'payment_history':
                $rows = DB::table('payments')->where('consumer_id', $cid)->orderByDesc('payment_date')->limit(3)->get();
                if ($rows->isEmpty()) return "You don't have any payments on record yet. Your full payment history is always available on the \"Payment History\" page.";
                $lines = $rows->map(fn ($p) => "• {$p->payment_reference} — " . formatCurrency($p->amount_paid) . " (" . paymentStatusLabel($p->status) . ")");
                return "Here are your most recent payments:\n" . $lines->implode("\n") . "\nSee the full list on the \"Payment History\" page.";

            case 'billing_history':
                $rows = DB::table('water_bills')->where('consumer_id', $cid)->orderByDesc('billing_period')->limit(3)->get();
                if ($rows->isEmpty()) return "You don't have any bills on record yet.";
                $lines = $rows->map(fn ($b) => '• ' . billingPeriodLabel($b->billing_period) . ' — ' . formatCurrency($b->total_amount) . ', ' . str_replace('_', ' ', $b->status));
                return "Here's a quick look at your recent bills:\n" . $lines->implode("\n") . "\nUnpaid bills are under \"Current Bills\"; paid ones under \"Billing History\".";

            case 'account_info':
                return "Meter Number {$consumer->meter_number} — {$consumer->full_name}, " . ($consumer->purok_name ?? 'your purok')
                    . ' (' . consumerTypeLabel($consumer->consumer_type ?? null) . "). Status: {$consumer->status}. Full details are on your \"My Profile\" page.";
        }
        return '';
    }

    /** Best keyword match among active FAQs, or null when nothing scores at least 1. */
    private function faqSearch(string $message): ?object
    {
        $faqs = DB::table('chatbot_faqs')->where('status', 'active')->get();
        if ($faqs->isEmpty()) return null;

        $stopwords = ['the', 'is', 'my', 'a', 'an', 'to', 'for', 'of', 'in', 'on', 'and', 'or', 'what', 'how', 'do', 'i', 'can', 'does', 'when', 'where', 'why', 'are', 'it', 'you', 'me', 'please'];
        $words = array_filter(
            preg_split('/[^a-z0-9]+/', strtolower($message)) ?: [],
            fn ($w) => strlen($w) > 2 && !in_array($w, $stopwords, true)
        );

        // Near-exact-phrase bonus lets a short quick-reply ("How can I pay?")
        // favor the FAQ it is a prefix of, instead of tying on one generic word.
        $messageNorm = trim(preg_replace('/[^a-z0-9 ]+/', '', strtolower($message)));

        $best = null;
        $bestScore = 0;
        foreach ($faqs as $faq) {
            $haystack = strtolower($faq->question . ' ' . $faq->keywords);
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($haystack, $w)) $score++;
            }
            $questionNorm = trim(preg_replace('/[^a-z0-9 ]+/', '', strtolower($faq->question)));
            if ($messageNorm !== '' && $questionNorm !== ''
                && (str_contains($questionNorm, $messageNorm) || str_contains($messageNorm, $questionNorm))) {
                $score += 5;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        return ($best && $bestScore >= 1) ? $best : null;
    }

    /**
     * Last-resort Claude API call for questions the FAQs don't cover. Returns
     * null when disabled/unconfigured or on any failure, so the caller falls
     * back to the canned "contact staff" reply.
     */
    private function aiFallback(string $message, array $faqExcerpts): ?string
    {
        $apiKey = (string)config('agas.chatbot.api_key');
        if ($apiKey === '' || $this->settings->get('chatbot_ai_enabled', '0') !== '1') {
            return null;
        }

        $context = '';
        foreach ($faqExcerpts as $f) {
            $context .= "- Q: {$f->question}\n  A: {$f->answer}\n";
        }

        $system = "You are \"AGAS Assistant\", a virtual help desk for residents of Barangay Adlay's AGAS water billing system. "
            . 'Answer ONLY questions about: AGAS, Barangay Adlay water services, water billing, meter readings, payments, '
            . 'resident accounts, and how to navigate the AGAS system. '
            . 'Use the reference FAQ excerpts below as your primary source of truth and do not contradict them. '
            . "You have no access to any specific resident's bill, payment, or account figures — never invent such numbers; "
            . 'tell the resident to check the relevant dashboard page instead. '
            . 'Never claim to verify payments, edit bills, or change meter readings — those require barangay staff. '
            . "Never reveal or guess a password. If the question is unrelated to AGAS/water billing, or you are unsure, "
            . "say you don't have enough information and to contact the Barangay Adlay water staff. "
            . "Keep answers under 80 words, friendly, and simple.\n\nReference FAQs:\n" . $context;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('agas.chatbot.model'),
                'max_tokens' => 220,
                'system'     => $system,
                'messages'   => [['role' => 'user', 'content' => $message]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chatbot AI fallback failed: ' . $e->getMessage());
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Chatbot AI fallback failed: HTTP ' . $response->status());
            return null;
        }

        foreach ($response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text' && trim($block['text'] ?? '') !== '') {
                return trim($block['text']);
            }
        }
        return null;
    }
}
