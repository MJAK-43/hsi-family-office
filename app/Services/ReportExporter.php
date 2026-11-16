<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exports Excel (plusieurs onglets) et PDF des rapports du fonds. */
class ReportExporter
{
    public const SECTIONS = [
        'summary' => 'Synthèse',
        'statement' => 'Relevé de compte',
        'members' => 'Registre des membres',
        'loans' => 'Prêts et remboursements',
        'movements' => 'Mouvements par type',
    ];

    public function xlsx(array $report, string $title, array $sections): StreamedResponse
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('HSI · Fonds familial JOKUNG')->setTitle($title);
        $book->removeSheetByIndex(0);

        foreach ($sections as $section) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr(__(self::SECTIONS[$section] ?? $section), 0, 31));
            $sheet->setCellValue('A1', __($title));
            $sheet->setCellValue('A2', __('Période du :from au :to — montants en :currency', ['from' => $report['period']['from'], 'to' => $report['period']['to'], 'currency' => \App\Support\FundCurrency::code()]));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            [$headers, $rows] = $this->table($report, $section);
            $sheet->fromArray(array_map(fn ($h) => \App\Support\FundCurrency::label(__($h)), $headers), null, 'A4');
            $sheet->fromArray(array_map(fn ($row) => array_map(fn ($v) => is_string($v) ? \App\Support\FundCurrency::label($v) : $v, $row), $rows), null, 'A5');
            $this->styleHeader($sheet, count($headers));
            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
        }
        $book->setActiveSheetIndex(0);

        return new StreamedResponse(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($title).'.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function pdf(array $report, string $title, array $sections, ?string $comment = null)
    {
        $tables = collect($sections)->mapWithKeys(fn ($section) => [$section => $this->table($report, $section)])->all();

        return Pdf::loadView('reports.pdf', [
            'title' => $title, 'report' => $report, 'tables' => $tables, 'sections' => self::SECTIONS, 'comment' => $comment,
            'generatedAt' => now()->timezone('Europe/Paris')->format('d/m/Y H:i'),
        ])->setPaper('a4', in_array('statement', $sections, true) || in_array('members', $sections, true) ? 'landscape' : 'portrait')
            ->download($this->filename($title).'.pdf');
    }

    /** @return array{0: array<int,string>, 1: array<int, array<int, mixed>>} */
    public function table(array $report, string $section): array
    {
        $money = fn ($v) => $v === null ? '' : round((float) $v, 2);

        return match ($section) {
            'summary' => [['Indicateur', 'Valeur'], [
                ['Cotisations reçues ($)', $money($report['kpis']['contributions'])],
                ['Nombre de cotisations', $report['kpis']['contributions_count']],
                ['Pénalités de cotisation ($)', $money($report['kpis']['contribution_penalties'])],
                ['Pénalités de remboursement ($)', $money($report['kpis']['loan_penalties'])],
                ['Cotisations en retard', $report['kpis']['late_contributions']],
                ['Échéances de prêt en retard', $report['kpis']['late_installments']],
                ['Solde total du compte ($)', $money($report['kpis']['balance'])],
                ['Décaissements ($)', $money($report['kpis']['disbursed'])],
                ['Remboursements reçus ($)', $money($report['kpis']['repayments'])],
                ['Reste dû sur les prêts en cours ($)', $money($report['kpis']['outstanding'])],
                ['Prêts en cours', $report['kpis']['active_loans']],
                ['Membres actifs', $report['kpis']['active_members']],
            ]],
            'statement' => [['Date', 'Compte', 'Type', 'Libellé', 'Référence', 'Crédit ($)', 'Débit ($)', 'Solde ($)'], [
                ['', '', '', __('Solde d’ouverture'), '', '', '', $money($report['statement']['opening'])],
                ...array_map(fn ($r) => [$r['date'], $r['account'], __($r['type']), $r['label'], $r['reference'], $money($r['credit']), $money($r['debit']), $money($r['balance'])], $report['statement']['rows']),
                ['', '', '', __('Solde de clôture'), '', $money($report['statement']['credits']), $money($report['statement']['debits']), $money($report['statement']['closing'])],
            ]],
            'members' => [['Membre', 'Zone', 'Statut', 'Cotisations ($)', 'Versements', 'Dernière cotisation', 'Part du fonds (%)', 'Prêt restant dû ($)', 'Pénalités dues ($)'],
                array_map(fn ($m) => [$m['name'], __($m['zone']), __($m['status']), $money($m['contributions']), $m['contributions_count'], $m['last_contribution'], $m['share_percent'], $money($m['loans_outstanding']), $money($m['penalties'])], $report['members'])],
            'loans' => [['Projet', 'Emprunteur', 'Versé le', 'Montant ($)', 'Total à rembourser ($)', 'Remboursé ($)', 'Reste dû ($)', 'Pénalités ($)', 'Prochaine échéance', 'État'],
                array_map(fn ($l) => [$l['title'], $l['borrower'], $l['funded_at'], $money($l['amount']), $money($l['total_due']), $money($l['paid']), $money($l['remaining']), $money($l['penalties']), $l['next_due'], __($l['status'])], $report['loans'])],
            'movements' => [['Type de mouvement', 'Nombre', 'Total ($)'],
                array_map(fn ($m) => [__($m['label']), $m['count'], $money($m['total'])], $report['movements_by_type'])],
            default => [[], []],
        };
    }

    private function styleHeader(Worksheet $sheet, int $columns): void
    {
        $range = 'A4:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns).'4';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B2748');
    }

    private function filename(string $title): string
    {
        return 'hsi-'.\Illuminate\Support\Str::slug($title).'-'.now()->format('Ymd');
    }
}
