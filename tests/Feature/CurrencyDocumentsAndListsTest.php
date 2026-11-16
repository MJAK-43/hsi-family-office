<?php

namespace Tests\Feature;

use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementType;
use App\Enums\PaymentMethod;
use App\Models\ApplicationSetting;
use App\Models\ApprovalRequest;
use App\Models\ContributionPayment;
use App\Models\ContributionPlan;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFolder;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Models\LoanInstallment;
use App\Models\MemberProfile;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectType;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\CurrencyConversionService;
use App\Services\FundService;
use App\Support\FundCurrency;
use App\Support\FundRules;
use App\Support\InternalPolicy;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CurrencyDocumentsAndListsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FundRules::flush();
        FundCurrency::flush();
        $this->seed([RolePermissionSeeder::class, CountrySeeder::class, FundAccountSeeder::class]);
        Notification::fake();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        FundRules::flush();
        FundCurrency::flush();
        parent::tearDown();
    }

    private function user(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->syncRoles($roles ?: ['member']);
        MemberProfile::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    // ------------------------------------------------------------------ Devise

    public function test_changing_the_currency_converts_every_amount_and_the_rules(): void
    {
        $admin = $this->user('super_admin');
        $member = $this->user('member');
        $account = FundAccount::where('slug', 'family-projects')->firstOrFail();
        app(FundService::class)->createMovement([
            'fund_account_id' => $account->id, 'type' => FundMovementType::ManualIncome, 'amount' => '1000.00',
            'currency' => 'USD', 'direction' => 'credit', 'label' => 'Apport',
        ]);
        $payment = ContributionPayment::create([
            'user_id' => $member->id, 'amount' => 75, 'currency' => 'USD', 'payment_method' => PaymentMethod::BankTransfer,
            'status' => ContributionPaymentStatus::Validated, 'submitted_at' => now(), 'paid_at' => now(), 'validated_at' => now(), 'created_by' => $member->id,
        ]);
        $project = Project::create([
            'owner_id' => $member->id, 'title' => 'Prêt test', 'description' => 'Test', 'total_budget' => 300, 'personal_contribution' => 0,
            'requested_amount' => 300, 'currency' => 'USD', 'sector' => 'agriculture', 'status' => 'in_progress', 'created_by' => $member->id,
        ]);
        $installment = LoanInstallment::create(['project_id' => $project->id, 'number' => 1, 'due_date' => now()->addMonth(), 'principal' => 99.01, 'interest' => 1.00, 'amount' => 100.01, 'paid_amount' => 100.01, 'status' => 'paid']);

        $this->actingAs($admin)->post(route('admin.currency.convert'), ['currency' => 'EUR', 'rate' => '0.5', 'confirm' => true])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.index', ['tab' => 'settings']));

        FundCurrency::flush();
        FundRules::flush();
        $this->assertSame('EUR', FundCurrency::code());
        $this->assertSame('500.00', FundMovement::where('fund_account_id', $account->id)->value('amount'));
        $this->assertSame('EUR', FundMovement::where('fund_account_id', $account->id)->value('currency'));
        $this->assertSame('500.00', (string) $account->fresh()->current_balance);
        $this->assertSame('37.50', (string) $payment->fresh()->amount);
        $this->assertSame('EUR', $payment->fresh()->currency);
        $this->assertSame('150.00', (string) $project->fresh()->requested_amount);
        $installment->refresh();
        // Capital + intérêts = échéance, et l'échéance soldée le reste après arrondi.
        $this->assertEqualsWithDelta((float) $installment->amount, (float) $installment->principal + (float) $installment->interest, 0.001);
        $this->assertSame((string) $installment->amount, (string) $installment->paid_amount);
        $this->assertEqualsWithDelta(37.5, FundRules::get('contribution_quarterly_amount'), 0.001);
        $this->assertEqualsWithDelta(1.0, FundRules::get('contribution_penalty_amount'), 0.001);
        // Le règlement affiche les montants convertis, dans la nouvelle devise.
        $text = json_encode(InternalPolicy::filled('fr'), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('37,50 € (12,50 € × 3 mois)', $text);
        $this->assertStringContainsString('pénalité de retard de 1 € par semaine', $text);
        $this->assertDatabaseHas('activity_log', ['event' => 'administration.currency.converted']);

        // Toutes les pages reçoivent la devise active.
        $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('currency.code', 'EUR')->where('currency.symbol', '€'));
    }

    public function test_currency_change_is_refused_while_operations_await_approval(): void
    {
        $admin = $this->user('super_admin');
        ApprovalRequest::create(['transaction_type_id' => TransactionType::firstOrFail()->id, 'title' => 'En attente', 'amount' => 10, 'currency' => 'USD', 'status' => 'pending', 'required_approvals' => 2, 'requested_by' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.currency.convert'), ['currency' => 'XAF', 'rate' => '600', 'confirm' => true])
            ->assertSessionHasErrors('currency');
        FundCurrency::flush();
        $this->assertSame('USD', FundCurrency::code());
    }

    public function test_only_settings_managers_can_change_the_currency_and_confirmation_is_required(): void
    {
        $member = $this->user('member');
        $this->actingAs($member)->post(route('admin.currency.convert'), ['currency' => 'EUR', 'rate' => '0.9', 'confirm' => true])->assertForbidden();

        $admin = $this->user('super_admin');
        $this->actingAs($admin)->post(route('admin.currency.convert'), ['currency' => 'EUR', 'rate' => '0.9'])->assertSessionHasErrors('confirm');
        $this->assertSame('USD', ApplicationSetting::where('key', 'default_currency')->value('value'));
    }

    public function test_admin_page_offers_the_currency_list_and_a_filtered_paginated_audit_log(): void
    {
        $admin = $this->user('super_admin');
        foreach (range(1, 30) as $i) {
            activity('administration')->causedBy($admin)->event('test.event')->log("Action numéro {$i}");
        }
        activity('documents')->causedBy($admin)->event('document.created')->log('Document créé spécial');

        $this->actingAs($admin)->get(route('admin.index', ['tab' => 'audit']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'audit')
                ->where('currency.current', 'USD')
                ->has('currency.options', 3)
                ->where('activities.total', fn ($total) => $total >= 31)
                ->has('activities.data', 25));

        $this->actingAs($admin)->get(route('admin.index', ['tab' => 'audit', 'audit_log' => 'documents', 'audit_search' => 'spécial']))
            ->assertInertia(fn (Assert $page) => $page->where('activities.total', 1)->where('activities.data.0.log', 'documents'));
    }

    // ------------------------------------------------------- Documents (explorateur)

    public function test_folders_can_be_created_filled_by_upload_and_documents_moved(): void
    {
        $secretary = $this->user('secretary');
        $category = DocumentCategory::create(['name' => 'Procès-verbaux', 'slug' => 'pv', 'is_active' => true]);

        $this->actingAs($secretary)->post(route('document-folders.store'), ['name' => 'Assemblées 2026'])->assertSessionHas('success');
        $folder = DocumentFolder::where('name', 'Assemblées 2026')->firstOrFail();
        $this->actingAs($secretary)->post(route('document-folders.store'), ['name' => 'Assemblées 2026'])->assertSessionHasErrors('name');
        $this->actingAs($secretary)->post(route('document-folders.store'), ['name' => 'Janvier', 'parent_id' => $folder->id])->assertSessionHas('success');
        $child = DocumentFolder::where('name', 'Janvier')->firstOrFail();

        // Dépôt de plusieurs fichiers dans le dossier.
        $this->actingAs($secretary)->post(route('documents.upload'), [
            'files' => [UploadedFile::fake()->create('pv-janvier.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('photo.jpg')],
            'folder_id' => $folder->id, 'document_category_id' => $category->id, 'visibility' => 'members',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, Document::where('folder_id', $folder->id)->count());
        $pv = Document::where('title', 'pv-janvier')->firstOrFail();

        // Un dossier ne peut pas aller dans son propre sous-dossier.
        $this->actingAs($secretary)->patch(route('document-folders.update', $folder), ['parent_id' => $child->id])->assertSessionHasErrors('folder');
        // Déplacement d'un document, puis suppression refusée tant que le dossier n'est pas vide.
        $this->actingAs($secretary)->patch(route('documents.move', $pv), ['folder_id' => $child->id])->assertSessionHas('success');
        $this->assertSame($child->id, $pv->fresh()->folder_id);
        $this->actingAs($secretary)->delete(route('document-folders.destroy', $child))->assertSessionHasErrors('folder');
        $this->actingAs($secretary)->patch(route('documents.move', $pv), ['folder_id' => null]);
        $this->actingAs($secretary)->delete(route('document-folders.destroy', $child))->assertSessionHas('success');

        $this->actingAs($secretary)->get(route('documents.index', ['folder' => $folder->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Index')
                ->where('location.type', 'folder')
                ->where('breadcrumbs.1.label', 'Assemblées 2026')
                ->has('files', 1)
                ->where('files.0.preview', 'image'));

        $this->actingAs($secretary)->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page->where('location.type', 'root')->where('folders.0.name', 'Assemblées 2026')->has('files', 1));
    }

    public function test_members_cannot_manage_folders_and_previews_are_served_inline(): void
    {
        $member = $this->user('member');
        $secretary = $this->user('secretary');
        $category = DocumentCategory::create(['name' => 'Statuts', 'slug' => 'statuts', 'is_active' => true]);

        $this->actingAs($member)->post(route('document-folders.store'), ['name' => 'Interdit'])->assertForbidden();

        $this->actingAs($secretary)->post(route('documents.upload'), [
            'files' => [UploadedFile::fake()->create('statuts.pdf', 50, 'application/pdf')],
            'document_category_id' => $category->id, 'visibility' => 'members',
        ])->assertSessionHasNoErrors();
        $document = Document::firstOrFail();

        $response = $this->actingAs($secretary)->get(route('documents.download', [$document, 'inline' => 1]));
        $response->assertOk();
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertDatabaseHas('activity_log', ['event' => 'document.previewed']);
        // Le téléchargement classique reste une pièce jointe.
        $this->assertStringStartsWith('attachment', $this->actingAs($secretary)->get(route('documents.download', $document))->headers->get('Content-Disposition'));
    }

    public function test_a_whole_folder_can_be_imported_with_its_subfolders(): void
    {
        $secretary = $this->user('secretary');
        $base = DocumentFolder::create(['name' => 'Archives', 'created_by' => $secretary->id]);

        $this->actingAs($secretary)->postJson(route('documents.upload'), [
            'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('b.png'), UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')],
            'paths' => ['Famille 2025/a.pdf', 'Famille 2025/Photos/b.png', '../c.pdf'],
            'folder_id' => $base->id, 'visibility' => 'members',
        ])->assertOk()->assertJson(['documents' => 3, 'folders' => 2]);

        $family = DocumentFolder::where('parent_id', $base->id)->where('name', 'Famille 2025')->firstOrFail();
        $photos = DocumentFolder::where('parent_id', $family->id)->where('name', 'Photos')->firstOrFail();
        $this->assertSame($family->id, Document::where('title', 'a')->value('folder_id'));
        $this->assertSame($photos->id, Document::where('title', 'b')->value('folder_id'));
        $this->assertSame($base->id, Document::where('title', 'c')->value('folder_id'));

        // Un second import réutilise les dossiers existants.
        $this->actingAs($secretary)->postJson(route('documents.upload'), [
            'files' => [UploadedFile::fake()->create('d.pdf', 10, 'application/pdf')], 'paths' => ['Famille 2025/Photos/d.pdf'],
            'folder_id' => $base->id, 'visibility' => 'members',
        ])->assertJson(['folders' => 0]);
        $this->assertSame($photos->id, Document::where('title', 'd')->value('folder_id'));
    }

    public function test_constitution_without_vote_shows_why_it_cannot_be_published(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->post(route('documents.store'), [
            'title' => 'Constitution draft', 'visibility' => 'members', 'is_constitution' => true,
            'file' => UploadedFile::fake()->create('constitution.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $document = Document::where('title', 'Constitution draft')->firstOrFail();

        $this->actingAs($admin)->get(route('documents.show', $document))
            ->assertInertia(fn (Assert $page) => $page->where('document.constitution_blocked', true));
        // Publication refusée avec un message affiché (flash « error »).
        $this->actingAs($admin)->patch(route('documents.publish', $document))->assertSessionHas('error');
        $this->assertSame('draft', $document->fresh()->status->value);

        // Mention retirée → publication possible.
        $this->actingAs($admin)->patch(route('documents.constitution', $document), ['is_constitution' => false])->assertSessionHas('success');
        $this->actingAs($admin)->patch(route('documents.publish', $document))->assertSessionHas('success');
        $this->assertSame('published', $document->fresh()->status->value);
    }

    public function test_default_categories_are_created_and_category_is_optional(): void
    {
        $secretary = $this->user('secretary');
        DocumentCategory::query()->delete();

        $this->actingAs($secretary)->get(route('documents.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Documents/Create')->has('categories', count(DocumentCategory::DEFAULTS)));

        $this->actingAs($secretary)->post(route('documents.store'), [
            'title' => 'Test document', 'visibility' => 'public', 'change_note' => 'Version initiale',
            'file' => UploadedFile::fake()->image('portrait.png'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('autres-documents', Document::where('title', 'Test document')->firstOrFail()->category->slug);
    }

    // ------------------------------------------------------- Projets et types

    public function test_a_project_can_be_created_with_attachments(): void
    {
        $member = $this->user('member');
        $type = ProjectType::where('code', 'investment')->firstOrFail();

        $this->actingAs($member)->post(route('projects.store'), [
            'project_type_id' => $type->id, 'fund_account_id' => FundAccount::where('slug', 'family-projects')->value('id'), 'title' => 'Boulangerie', 'description' => 'Ouverture d’une boulangerie.',
            'country_id' => null, 'sector' => 'agriculture', 'total_budget' => 1000, 'personal_contribution' => 500, 'requested_amount' => 500, 'currency' => 'USD',
            'attachments' => [
                ['file' => UploadedFile::fake()->create('business-plan.pdf', 200, 'application/pdf'), 'type' => 'business_plan', 'title' => 'Business plan'],
                ['file' => UploadedFile::fake()->create('devis.xlsx', 20), 'type' => 'quote', 'title' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $project = Project::where('title', 'Boulangerie')->firstOrFail();
        $this->assertSame(2, ProjectDocument::where('project_id', $project->id)->count());
        $this->assertSame('Business plan', ProjectDocument::where('type', 'business_plan')->value('title'));
        Storage::disk('local')->assertExists(ProjectDocument::where('type', 'quote')->value('file_path'));

        // Un format interdit est refusé avec un message clair.
        $this->actingAs($member)->post(route('projects.store'), [
            'project_type_id' => $type->id, 'fund_account_id' => FundAccount::where('slug', 'family-projects')->value('id'), 'title' => 'Autre', 'description' => 'Test', 'sector' => 'agriculture',
            'total_budget' => 1000, 'personal_contribution' => 500, 'requested_amount' => 500, 'currency' => 'USD',
            'attachments' => [['file' => UploadedFile::fake()->create('script.exe', 5), 'type' => 'other']],
        ])->assertSessionHasErrors('attachments.0.file');
    }

    public function test_new_project_types_can_reuse_existing_criteria(): void
    {
        $committee = $this->user('investment_committee');
        $loan = ProjectType::where('code', 'loan')->with('criteria')->firstOrFail();
        $picked = $loan->criteria->take(3);

        $this->actingAs($committee)->get(route('project-types.index'))
            ->assertInertia(fn (Assert $page) => $page->has('criteriaLibrary'));

        $this->actingAs($committee)->post(route('project-types.store'), [
            'name' => 'Urgence familiale', 'is_loan' => true, 'requires_personal_contribution' => false, 'is_active' => true,
            'copy_criteria_ids' => $picked->pluck('id')->all(),
        ])->assertSessionHas('success');
        $type = ProjectType::where('name', 'Urgence familiale')->firstOrFail();
        $this->assertSame($picked->pluck('label')->all(), $type->criteria()->pluck('label')->all());
        $this->assertSame($picked->pluck('auto_check')->all(), $type->criteria()->pluck('auto_check')->all());

        // Reprise depuis un type existant : les doublons sont ignorés.
        $more = $loan->criteria->slice(2, 3)->pluck('id')->all();
        $this->actingAs($committee)->post(route('project-types.criteria.import', $type), ['criteria_ids' => $more])->assertSessionHas('success');
        $this->assertSame(5, $type->criteria()->count());
        $this->assertSame([1, 2, 3, 4, 5], $type->criteria()->pluck('position')->all());

        $this->actingAs($this->user('member'))->post(route('project-types.criteria.import', $type), ['criteria_ids' => $more])->assertForbidden();
    }

    public function test_contribution_plan_and_project_forms_use_the_fund_currency(): void
    {
        ApplicationSetting::where('key', 'default_currency')->update(['value' => 'XAF']);
        FundCurrency::flush();
        $this->assertSame('1 500 FCFA', FundCurrency::format(1500));
        $this->assertSame('Montant (FCFA)', FundCurrency::label('Montant ($)'));
        $treasurer = $this->user('treasurer');
        $this->actingAs($treasurer)->get(route('contribution-plans.create'))
            ->assertInertia(fn (Assert $page) => $page->where('currencies', ['XAF'])->where('currency.symbol', 'FCFA'));
    }
}
