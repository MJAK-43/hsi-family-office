<?php

namespace Tests\Feature;

use App\Models\TestFeedback;
use App\Support\Recette\RecettePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecetteTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'code-secret-de-test-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['recette.token' => self::TOKEN]);
        Storage::fake('local');
    }

    public function test_the_page_needs_the_secret_code(): void
    {
        $this->get('/recette/mauvais-code')->assertNotFound();
        $this->post('/recette/mauvais-code/retours', [])->assertNotFound();

        config(['recette.token' => null]);
        $this->get('/recette/'.self::TOKEN)->assertNotFound();
    }

    public function test_the_page_lists_the_seven_days_without_an_account(): void
    {
        $this->get('/recette/'.self::TOKEN)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Recette/Index')
                ->has('days', 7)
                ->where('days.0.module', 'Connexion et adhésion')
                ->where('days.6.module', 'Cotisations')
                ->where('days.5.module', 'Fonds familial'));

        $this->assertCount(count(array_unique(RecettePlan::taskKeys())), RecettePlan::taskKeys(), 'Les clés de tâches doivent être uniques.');
    }

    public function test_the_page_lists_every_role_and_what_it_can_do(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        \App\Models\User::factory()->create()->assignRole('investment_committee');

        $this->get('/recette/'.self::TOKEN)
            ->assertInertia(fn (Assert $page) => $page
                ->has('roles', 10)
                ->where('roles.0.name', 'super_admin')
                ->where('roles.5.name', 'investment_committee')
                ->where('roles.5.label', 'Comité investissement')
                ->where('roles.5.users_count', 1)
                ->where('roles.5.groups', fn ($groups) => in_array(
                    'Approuver un projet',
                    collect($groups)->firstWhere('module', 'Projets')['actions'] ?? [],
                    true,
                ))
                ->where('roles.9.name', 'guest')
                ->where('roles.9.actions_count', 1)
                // Chaque permission du seeder a une description en clair.
                ->where('roles', fn ($roles) => collect($roles)->every(fn ($r) => ! collect($r['groups'])->contains('module', 'Autres'))));

        $english = json_decode(file_get_contents(lang_path('en.json')), true);
        foreach (\App\Support\Recette\RolesOverview::texts() as $text) {
            $this->assertArrayHasKey($text, $english, "Traduction anglaise manquante : $text");
        }
    }

    public function test_the_plan_is_shown_in_english_for_english_speaking_visitors(): void
    {
        $this->withHeaders(['CF-IPCountry' => 'GB'])->get('/recette/'.self::TOKEN)
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.0.module', 'Sign-in and membership')
                ->where('days.0.tasks.0.title', 'Submit a membership application')
                ->where('days.6.module', 'Contributions'));

        // Chaque tâche a sa version anglaise complète.
        $english = \App\Support\Recette\RecettePlanEnglish::tasks();
        foreach (RecettePlan::taskKeys() as $key) {
            $this->assertArrayHasKey($key, $english);
            $this->assertNotEmpty($english[$key]['steps']);
        }
        $this->assertCount(count(RecettePlan::taskKeys()), $english);
    }

    public function test_a_tester_validates_a_task(): void
    {
        $this->post('/recette/'.self::TOKEN.'/retours', [
            'tester_name' => '  Marie   K. ',
            'task_key' => '1.1',
            'status' => 'validated',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('test_feedback', ['task_key' => '1.1', 'tester_name' => 'Marie K.', 'tester_key' => 'marie k.', 'status' => 'validated']);
    }

    public function test_to_review_needs_a_comment_and_accepts_several_attachments(): void
    {
        $url = '/recette/'.self::TOKEN.'/retours';

        $this->post($url, ['tester_name' => 'Paul', 'task_key' => '2.1', 'status' => 'to_review'])
            ->assertSessionHasErrors('comment');

        $this->post($url, [
            'tester_name' => 'Paul',
            'task_key' => '2.1',
            'status' => 'to_review',
            'comment' => "L'e-mail de bienvenue est arrivé dans les spams.",
            'attachments' => [
                UploadedFile::fake()->image('capture1.png'),
                UploadedFile::fake()->image('capture2.jpg'),
                UploadedFile::fake()->create('rapport.pdf', 200, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $feedback = TestFeedback::with('attachments')->firstOrFail();
        $this->assertCount(3, $feedback->attachments);
        foreach ($feedback->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }

        // Les pièces jointes ne s'ouvrent qu'avec le code secret.
        $attachment = $feedback->attachments->first();
        $this->get('/recette/'.self::TOKEN.'/fichiers/'.$attachment->id)->assertOk();
        $this->get('/recette/autre-code/fichiers/'.$attachment->id)->assertNotFound();
    }

    public function test_forbidden_files_and_unknown_tasks_are_rejected(): void
    {
        $url = '/recette/'.self::TOKEN.'/retours';

        $this->post($url, ['tester_name' => 'Awa', 'task_key' => '99.9', 'status' => 'validated'])->assertSessionHasErrors('task_key');
        $this->post($url, ['tester_name' => 'Awa', 'task_key' => '1.1', 'status' => 'peut-être'])->assertSessionHasErrors('status');
        $this->post($url, [
            'tester_name' => 'Awa', 'task_key' => '1.1', 'status' => 'to_review', 'comment' => 'Bug',
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('test_feedback', 0);
    }

    public function test_several_testers_and_history_are_kept_and_exported(): void
    {
        $url = '/recette/'.self::TOKEN.'/retours';
        $this->post($url, ['tester_name' => 'Marie', 'task_key' => '3.1', 'status' => 'to_review', 'comment' => 'Erreur à l’enregistrement']);
        $this->post($url, ['tester_name' => 'marie', 'task_key' => '3.1', 'status' => 'validated']);
        $this->post($url, ['tester_name' => 'Paul', 'task_key' => '3.1', 'status' => 'validated']);

        $this->assertSame(2, TestFeedback::where('tester_key', 'marie')->count(), 'Le changement d’avis est conservé dans l’historique.');

        $this->get('/recette/'.self::TOKEN)->assertInertia(fn (Assert $page) => $page->has('entries', 3));

        $csv = $this->get('/recette/'.self::TOKEN.'/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Déposer un projet', $csv);
        $this->assertStringContainsString('À revoir', $csv);
        $this->assertStringContainsString('Paul', $csv);
    }

    public function test_a_review_can_be_answered_and_marked_as_fixed(): void
    {
        $feedback = TestFeedback::create(['task_key' => '5.1', 'tester_name' => 'Romy', 'tester_key' => 'romy', 'status' => TestFeedback::STATUS_TO_REVIEW, 'comment' => 'Publish not working?']);

        $this->post('/recette/'.self::TOKEN.'/retours/'.$feedback->id.'/reponses', ['author_name' => 'Andy', 'body' => ''])
            ->assertSessionHasErrors('body');
        $this->post('/recette/'.self::TOKEN.'/retours/'.$feedback->id.'/reponses', ['author_name' => ' Andy  Karl ', 'body' => 'Corrigé : liez le vote ou décochez Constitution.', 'marks_fixed' => true])
            ->assertSessionHas('success');
        $this->post('/recette/mauvais-code/retours/'.$feedback->id.'/reponses', ['author_name' => 'X', 'body' => 'x'])->assertNotFound();

        $this->get('/recette/'.self::TOKEN)
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.replies.0.author_name', 'Andy Karl')
                ->where('entries.0.replies.0.author_key', 'andy karl')
                ->where('entries.0.replies.0.marks_fixed', true));

        $csv = $this->get('/recette/'.self::TOKEN.'/export')->streamedContent();
        $this->assertStringContainsString('[corrigé]', $csv);
    }
}
