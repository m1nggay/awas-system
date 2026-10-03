<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotFaq;
use App\Services\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The AWAS Assistant knowledge base, unanswered-question log and AI fallback switch. */
class ChatbotFaqController extends Controller
{
    public function index(Settings $settings)
    {
        return view('admin.faqs.index', [
            'faqs'            => ChatbotFaq::orderByDesc('hit_count')->orderBy('question')->get(),
            'unanswered'      => DB::table('chatbot_unanswered as u')->leftJoin('users as us', 'us.user_id', '=', 'u.asked_by')
                ->orderByDesc('u.created_at')->limit(20)->get(['u.*', 'us.full_name as asked_by_name']),
            'categories'      => ChatbotFaq::CATEGORIES,
            'aiEnabled'       => $settings->get('chatbot_ai_enabled', '0') === '1',
            'aiKeyConfigured' => (string)config('agas.chatbot.api_key') !== '',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->fields($request);
        if (!$data) {
            flash('danger', 'Question and answer are both required.');
            return back();
        }
        try {
            ChatbotFaq::create($data + ['created_by' => $request->user()->user_id]);
            log_activity($request->user()->user_id, 'faq_add', "Added chatbot FAQ: {$data['question']}");

            // If this FAQ answers a previously-unanswered question, clear it out.
            if ($srcId = (int)$request->input('source_unanswered_id', 0)) {
                DB::table('chatbot_unanswered')->where('id', $srcId)->delete();
            }
            flash('success', 'FAQ added.');
        } catch (QueryException $e) {
            flash('danger', 'A FAQ with that exact question already exists.');
        }
        return back();
    }

    public function update(Request $request, ChatbotFaq $faq)
    {
        $data = $this->fields($request);
        if (!$data) {
            flash('danger', 'Question and answer are both required.');
            return back();
        }
        try {
            $faq->update($data);
            log_activity($request->user()->user_id, 'faq_edit', "Edited chatbot FAQ #{$faq->id}");
            flash('success', 'FAQ updated.');
        } catch (QueryException $e) {
            flash('danger', 'A FAQ with that exact question already exists.');
        }
        return back();
    }

    public function toggle(ChatbotFaq $faq)
    {
        $faq->update(['status' => $faq->status === 'active' ? 'inactive' : 'active']);
        flash('success', 'FAQ status updated.');
        return back();
    }

    public function destroy(Request $request, ChatbotFaq $faq)
    {
        $faq->delete();
        log_activity($request->user()->user_id, 'faq_delete', "Deleted chatbot FAQ #{$faq->id}");
        flash('success', 'FAQ deleted.');
        return back();
    }

    public function dismissUnanswered(int $id)
    {
        DB::table('chatbot_unanswered')->where('id', $id)->delete();
        flash('success', 'Dismissed.');
        return back();
    }

    public function updateAiSettings(Request $request, Settings $settings)
    {
        $enabled = $request->boolean('chatbot_ai_enabled') ? '1' : '0';
        $settings->set('chatbot_ai_enabled', $enabled);
        log_activity($request->user()->user_id, 'chatbot_ai_settings', "Set chatbot_ai_enabled=$enabled");
        flash('success', 'AI assistant settings updated.');
        return back();
    }

    private function fields(Request $request): ?array
    {
        $question = clean($request->input('question'));
        $answer = clean($request->input('answer'));
        if ($question === '' || $answer === '') {
            return null;
        }
        $category = clean($request->input('category', 'general'));
        return [
            'question' => $question,
            'answer'   => $answer,
            'category' => isset(ChatbotFaq::CATEGORIES[$category]) ? $category : 'general',
            'keywords' => clean($request->input('keywords')) ?: null,
            'status'   => $request->boolean('is_active') ? 'active' : 'inactive',
        ];
    }
}
