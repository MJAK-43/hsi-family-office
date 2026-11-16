<?php

namespace App\Http\Controllers;

use App\Models\TestFeedback;
use App\Models\TestFeedbackAttachment;
use App\Models\TestFeedbackReply;
use App\Support\Recette\RecettePlan;
use App\Support\Recette\RolesOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page de recette accessible sans compte, protégée par un code secret dans l'adresse.
 * Les testeurs donnent seulement leur prénom ; chaque envoi est conservé (historique).
 */
class RecetteController extends Controller
{
    public function index(string $token): Response
    {
        $this->guard($token);

        $entries = TestFeedback::query()
            ->with(['attachments', 'replies'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TestFeedback $f) => [
                'id' => $f->id,
                'task_key' => $f->task_key,
                'tester_name' => $f->tester_name,
                'tester_key' => $f->tester_key,
                'status' => $f->status,
                'comment' => $f->comment,
                'created_at' => $f->created_at?->toIso8601String(),
                'attachments' => $f->attachments->map(fn (TestFeedbackAttachment $a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'mime' => $a->mime_type,
                    'size' => $a->size,
                    'url' => route('recette.attachment', [$token, $a]),
                ])->values(),
                'replies' => $f->replies->map(fn (TestFeedbackReply $r) => [
                    'id' => $r->id,
                    'author_name' => $r->author_name,
                    'author_key' => $r->author_key,
                    'body' => $r->body,
                    'marks_fixed' => $r->marks_fixed,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values(),
            ]);

        return Inertia::render('Recette/Index', [
            'days' => RecettePlan::localized(app()->getLocale()),
            'entries' => $entries,
            'token' => $token,
            'limits' => ['max_files' => config('recette.max_files'), 'max_file_mb' => (int) (config('recette.max_file_kb') / 1024)],
            'today' => now()->toDateString(),
            'roles' => RolesOverview::all(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->guard($token);

        $data = $request->validate([
            'tester_name' => ['required', 'string', 'min:2', 'max:60'],
            'task_key' => ['required', Rule::in(RecettePlan::taskKeys())],
            'status' => ['required', Rule::in(TestFeedback::STATUSES)],
            'comment' => ['nullable', 'string', 'max:5000', Rule::requiredIf($request->input('status') === TestFeedback::STATUS_TO_REVIEW)],
            'attachments' => ['nullable', 'array', 'max:'.config('recette.max_files')],
            'attachments.*' => ['file', 'max:'.config('recette.max_file_kb'),
                'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf,mp4,mov,webm,txt,doc,docx,xls,xlsx'],
        ], [
            'tester_name.required' => __('Indiquez votre prénom.'),
            'comment.required' => __('Décrivez ce qui ne va pas pour que la tâche puisse être corrigée.'),
            'attachments.max' => __('Au maximum :max pièces jointes par retour.'),
            'attachments.*.max' => __('Chaque pièce jointe doit faire moins de :mb Mo.', ['mb' => (int) (config('recette.max_file_kb') / 1024)]),
            'attachments.*.mimes' => __('Formats acceptés : images, PDF, vidéos (mp4, mov, webm), Word, Excel ou texte.'),
        ]);

        $name = preg_replace('/\s+/u', ' ', trim($data['tester_name']));
        $isReview = $data['status'] === TestFeedback::STATUS_TO_REVIEW;
        $stored = [];

        try {
            DB::transaction(function () use ($data, $name, $isReview, $request, &$stored) {
                $feedback = TestFeedback::create([
                    'task_key' => $data['task_key'],
                    'tester_name' => $name,
                    'tester_key' => TestFeedback::testerKey($name),
                    'status' => $data['status'],
                    'comment' => $isReview ? trim((string) $data['comment']) : (filled($data['comment'] ?? null) ? trim($data['comment']) : null),
                    'ip_address' => $request->ip(),
                ]);

                if ($isReview) {
                    foreach ($request->file('attachments', []) as $file) {
                        $path = $file->store(config('recette.directory').'/'.$feedback->id, config('recette.disk'));
                        $stored[] = $path;
                        $feedback->attachments()->create([
                            'path' => $path,
                            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Storage::disk(config('recette.disk'))->delete($stored);
            throw $e;
        }

        return back()->with('success', __('Merci :name, votre retour sur la tâche :task est enregistré.', ['name' => $name, 'task' => $data['task_key']]));
    }

    /** Réponse à un retour (explication, correction effectuée, question…). */
    public function reply(Request $request, string $token, TestFeedback $feedback): RedirectResponse
    {
        $this->guard($token);

        $data = $request->validate([
            'author_name' => ['required', 'string', 'min:2', 'max:60'],
            'body' => ['required', 'string', 'max:3000'],
            'marks_fixed' => ['boolean'],
        ], [
            'author_name.required' => __('Indiquez votre prénom.'),
            'body.required' => __('Écrivez votre réponse.'),
        ]);

        $name = preg_replace('/\s+/u', ' ', trim($data['author_name']));
        $feedback->replies()->create([
            'author_name' => $name,
            'author_key' => TestFeedback::testerKey($name),
            'body' => trim($data['body']),
            'marks_fixed' => (bool) ($data['marks_fixed'] ?? false),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', __('Réponse envoyée à :name.', ['name' => $feedback->tester_name]));
    }

    public function attachment(string $token, TestFeedbackAttachment $attachment): StreamedResponse
    {
        $this->guard($token);

        $disk = Storage::disk(config('recette.disk'));
        abort_unless($disk->exists($attachment->path), 404);

        $inline = str_starts_with((string) $attachment->mime_type, 'image/')
            || str_starts_with((string) $attachment->mime_type, 'video/')
            || $attachment->mime_type === 'application/pdf';

        return $inline
            ? $disk->response($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff'])
            : $disk->download($attachment->path, $attachment->original_name);
    }

    /** Export Excel (CSV) : une ligne par envoi, historique compris. */
    public function export(string $token): StreamedResponse
    {
        $this->guard($token);
        $index = RecettePlan::taskIndex();

        return response()->streamDownload(function () use ($index, $token) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // accents corrects dans Excel
            fputcsv($out, ['Jour', 'Module', 'Tâche', 'Intitulé', 'Testeur', 'Résultat', 'Commentaire', 'Pièces jointes', 'Date', 'Réponses'], ';');

            TestFeedback::query()->with(['attachments', 'replies'])->orderBy('task_key')->orderBy('created_at')->chunk(200, function ($rows) use ($out, $index, $token) {
                foreach ($rows as $f) {
                    $info = $index[$f->task_key] ?? null;
                    fputcsv($out, [
                        $info ? 'Jour '.$info['day']['number'].' ('.$info['day']['date'].')' : '',
                        $info['day']['module'] ?? '',
                        $f->task_key,
                        $info['task']['title'] ?? '',
                        $f->tester_name,
                        TestFeedback::STATUS_LABELS[$f->status] ?? $f->status,
                        $f->comment,
                        $f->attachments->map(fn ($a) => route('recette.attachment', [$token, $a]))->implode(' '),
                        $f->created_at?->format('d/m/Y H:i'),
                        $f->replies->map(fn ($r) => $r->author_name.' ('.$r->created_at?->format('d/m H:i').')'.($r->marks_fixed ? ' [corrigé]' : '').' : '.$r->body)->implode(' | '),
                    ], ';');
                }
            });
            fclose($out);
        }, 'recette-hsi-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function guard(string $token): void
    {
        $expected = (string) config('recette.token');
        abort_if($expected === '' || ! hash_equals($expected, $token), 404);
    }
}
