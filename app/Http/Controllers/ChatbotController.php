<?php

namespace App\Http\Controllers;

use App\Services\Chatbot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** JSON endpoint for the AGAS Assistant widget (residents only; CSRF-checked by Laravel). */
class ChatbotController extends Controller
{
    public function __invoke(Request $request, Chatbot $chatbot)
    {
        $message = clean((string)$request->input('message', ''));
        if ($message === '') {
            return response()->json(['reply' => "Please type a question and I'll do my best to help!"]);
        }

        // Always the LOGGED-IN resident's own account — never one named in the chat text.
        $consumer = DB::table('consumers as c')
            ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->where('c.user_id', $request->user()->user_id)
            ->first(['c.*', 'p.purok_name']);

        return response()->json($chatbot->respond($message, $consumer, $request->user()->user_id));
    }
}
