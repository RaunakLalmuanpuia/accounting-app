<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AccountingAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Ai\Files;

class AiChatController extends Controller
{
    /**
     * Render the Accounting Chat page.
     *
     * Pass a null conversationId so the frontend starts a fresh session.
     * If you want to resume a previous session, look up the user's latest
     * conversation from `agent_conversations` and pass its ID here.
     */
    public function index()
    {
        return Inertia::render('Accounting/Chat', [
            'conversationId' => null,
        ]);
    }

    /**
     * Handle a chat message sent via Inertia router.post().
     *
     * Form fields:
     *   message          string (required)
     *   conversation_id  string|null  UUID of existing conversation to continue
     *   attachments[]    file[] (optional) — PDFs, images, spreadsheets
     *
     * Flashes to shared Inertia props (via HandleInertiaRequests):
     *   chatResponse.reply           string
     *   chatResponse.conversation_id string
     */
    public function send(Request $request)
    {
        $request->validate([
            'message'          => ['required', 'string', 'max:4000'],
            'conversation_id'  => ['nullable', 'string', 'uuid'],
            'attachments'      => ['nullable', 'array', 'max:5'],
            'attachments.*'    => [
                'file',
                'max:20480', // 20 MB per file
                'mimes:pdf,csv,xlsx,xls,docx,doc,txt,png,jpg,jpeg,webp',
            ],
        ]);

        $user    = $request->user();
        $message = $request->input('message');

        // ── Build attachment list for the AI SDK ──────────────────────
        $attachments = [];

        foreach ($request->file('attachments', []) as $uploadedFile) {
            try {
                $mime = $uploadedFile->getMimeType();

                $attachments[] = str_starts_with($mime, 'image/')
                    ? Files\Image::fromUpload($uploadedFile)
                    : Files\Document::fromUpload($uploadedFile);
            } catch (\Throwable $e) {
                Log::warning('[AI Chat] Attachment upload failed', [
                    'file'  => $uploadedFile->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
                // Skip broken attachments rather than failing the whole request
            }
        }

        // ── Build and configure the agent ─────────────────────────────
        $agent          = new AccountingAssistant($user);
        $conversationId = $request->input('conversation_id');

        $agent = $conversationId
            ? $agent->continue($conversationId, as: $user)  // resume existing conversation
            : $agent->forUser($user);                        // start a new conversation

        // ── Prompt the agent (synchronous) ────────────────────────────
        try {
            $response = $agent->prompt(
                prompt:      $message,
                attachments: $attachments,
            );

            return back()->with('chatResponse', [
                'reply'           => (string) $response,
                'conversation_id' => $response->conversationId ?? $conversationId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[AI Chat] AccountingAssistant error', [
                'user_id' => $user->id,
                'message' => $message,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'ai' => 'The assistant encountered an error. Please try again in a moment.',
            ]);
        }
    }
}
