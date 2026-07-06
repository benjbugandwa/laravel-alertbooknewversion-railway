<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;

trait FormatsWorksheetAsTable
{
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $worksheet = $event->sheet->getDelegate();
                $columnCount = count($this->headings());

                if ($columnCount < 1) {
                    return;
                }

                $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
                $lastRow = max(1, $worksheet->getHighestDataRow());
                $range = "A1:{$lastColumn}{$lastRow}";

                $worksheet->freezePane('A2');
                $worksheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                ]);
                $worksheet->getStyle($range)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);

                if ($lastRow < 2) {
                    $worksheet->setAutoFilter("A1:{$lastColumn}1");

                    return;
                }

                $table = new Table($range, $this->worksheetTableName());
                $style = new TableStyle;
                $style->setTheme(TableStyle::TABLE_STYLE_MEDIUM2);
                $style->setShowRowStripes(true);
                $table->setStyle($style);

                $worksheet->addTable($table);
            },
        ];
    }

    protected function worksheetTableName(): string
    {
        $base = method_exists($this, 'title') ? $this->title() : class_basename($this);
        $name = preg_replace('/[^A-Za-z0-9_]/', '_', $base);

        return substr('tbl_'.trim($name, '_'), 0, 255);
    }
}
