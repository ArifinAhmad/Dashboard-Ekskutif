<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExcelService
{
    public function __construct(private BrandingService $branding) {}

    public function download(array $sections, string $moduleLabel, string $reportLabel, CarbonImmutable $from, CarbonImmutable $to, string $filename): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle($reportLabel)
            ->setSubject($moduleLabel)
            ->setDescription('Laporan '.$reportLabel.' periode '.$from->format('d/m/Y').' - '.$to->format('d/m/Y'));
        $usedNames = [];
        $hospital = $this->branding->hospitalProfile();
        $contact = collect([$hospital['phone'], $hospital['email'], $hospital['website']])->filter()->implode(' | ');

        foreach ($sections as $index => $section) {
            $sheet = $spreadsheet->createSheet();
            $name = $this->uniqueSheetName($section['title'] ?: 'Data '.($index + 1), $usedNames);
            $sheet->setTitle($name);
            $lastColumn = Coordinate::stringFromColumnIndex(max(2, count($section['columns']) + 1));

            if ($logoPath = $this->branding->exportLogoPath()) {
                $drawing = new Drawing;
                $drawing->setPath(storage_path('app/private/'.$logoPath));
                $drawing->setCoordinates('A1');
                $drawing->setHeight(64);
                $drawing->setOffsetX(5);
                $drawing->setOffsetY(3);
                $drawing->setWorksheet($sheet);
            }

            $sheet->mergeCells("B1:{$lastColumn}1")->setCellValue('B1', $hospital['name']);
            $sheet->mergeCells("B2:{$lastColumn}2")->setCellValue('B2', $hospital['address'] ?: config('app.name'));
            $sheet->mergeCells("B3:{$lastColumn}3")->setCellValue('B3', $contact ?: 'Sistem '.$moduleLabel);
            $sheet->mergeCells("A4:{$lastColumn}4")->setCellValue('A4', strtoupper($moduleLabel.' - '.$reportLabel));
            $sheet->mergeCells("A5:{$lastColumn}5")->setCellValue('A5', $section['title'].' | Periode '.$from->format('d/m/Y').' sampai '.$to->format('d/m/Y'));
            $sheet->setCellValue('A6', 'No');
            foreach (array_values($section['columns']) as $columnIndex => $label) {
                $sheet->setCellValue([$columnIndex + 2, 6], $label);
            }

            $rowIndex = 7;
            foreach ($section['rows'] as $indexRow => $row) {
                $sheet->setCellValue([1, $rowIndex], $indexRow + 1);
                foreach (array_keys($section['columns']) as $columnIndex => $key) {
                    $value = data_get($row, $key);
                    $numeric = in_array($section['formats'][$key] ?? null, ['number', 'currency'], true) && is_numeric($value);
                    $sheet->setCellValue([$columnIndex + 2, $rowIndex], $numeric ? (float) $value : $this->safeCell((string) $value));
                }
                $rowIndex++;
            }

            $sheet->getStyle("B1:{$lastColumn}1")->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF16643F');
            $sheet->getStyle("B2:{$lastColumn}2")->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FF43574D');
            $sheet->getStyle("B3:{$lastColumn}3")->getFont()->setSize(9)->getColor()->setARGB('FF687A70');
            $sheet->getStyle("A4:{$lastColumn}4")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A4:{$lastColumn}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF237A50');
            $sheet->getStyle("A4:{$lastColumn}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A5:{$lastColumn}5")->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF9B531F');
            $sheet->getStyle("A5:{$lastColumn}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2E5');
            $sheet->getStyle("A5:{$lastColumn}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A6:{$lastColumn}6")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A6:{$lastColumn}6")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16593B');
            $sheet->getStyle("A6:{$lastColumn}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            $sheet->getStyle("A1:{$lastColumn}6")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B1:{$lastColumn}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

            $lastDataRow = max(6, $rowIndex - 1);
            $dataRange = "A6:{$lastColumn}{$lastDataRow}";
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCAD8D0');
            $sheet->getStyle("A7:{$lastColumn}{$lastDataRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $sheet->getStyle("A7:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            for ($stripeRow = 8; $stripeRow <= $lastDataRow; $stripeRow += 2) {
                $sheet->getStyle("A{$stripeRow}:{$lastColumn}{$stripeRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF6FAF7');
            }
            foreach (array_keys($section['columns']) as $columnIndex => $key) {
                $column = Coordinate::stringFromColumnIndex($columnIndex + 2);
                $format = $section['formats'][$key] ?? null;
                if ($format === 'currency') {
                    $sheet->getStyle("{$column}7:{$column}{$lastDataRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
                    $sheet->getStyle("{$column}7:{$column}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } elseif ($format === 'number') {
                    $sheet->getStyle("{$column}7:{$column}{$lastDataRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    $sheet->getStyle("{$column}7:{$column}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            $sheet->freezePane('A7');
            $sheet->setAutoFilter("A6:{$lastColumn}6");
            $sheet->getRowDimension(1)->setRowHeight(26);
            $sheet->getRowDimension(2)->setRowHeight(19);
            $sheet->getRowDimension(3)->setRowHeight(18);
            $sheet->getRowDimension(4)->setRowHeight(22);
            $sheet->getRowDimension(5)->setRowHeight(20);
            $sheet->getRowDimension(6)->setRowHeight(24);
            $sheet->getColumnDimension('A')->setWidth(7);
            foreach ($section['columns'] as $key => $label) {
                $columnIndex = array_search($key, array_keys($section['columns']), true);
                $column = Coordinate::stringFromColumnIndex($columnIndex + 2);
                $format = $section['formats'][$key] ?? null;
                $sheet->getColumnDimension($column)->setWidth(in_array($format, ['number', 'currency'], true) ? 16 : min(36, max(16, mb_strlen((string) $label) + 6)));
            }
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 6);
            $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.5)->setLeft(0.3)->setHeader(0.15)->setFooter(0.2);
            $sheet->getHeaderFooter()->setOddFooter('&L'.$hospital['name'].' | '.config('app.name').'&RHalaman &P dari &N');
            $sheet->getPageSetup()->setPrintArea("A1:{$lastColumn}{$lastDataRow}");
            $sheet->setShowGridlines(false);
        }

        $spreadsheet->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: private, no-store');
        (new Xlsx($spreadsheet))->save('php://output');
        $spreadsheet->disconnectWorksheets();
    }

    private function uniqueSheetName(string $name, array &$usedNames): string
    {
        $base = mb_substr(preg_replace('~[\\/?*\[\]:]~', '-', $name), 0, 28) ?: 'Data';
        $candidate = $base;
        $suffix = 2;
        while (isset($usedNames[$candidate])) {
            $candidate = mb_substr($base, 0, 27).'-'.$suffix++;
        }
        $usedNames[$candidate] = true;

        return $candidate;
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}
