<?php

namespace App\Services;

use App\Models\Athlete;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 選手登録用のエクセルシート（ひな形）。
 * 学年・性別・所属陸協・種別は選択式にして、そのまま「選手のまとめて登録」でアップロードできる形にする。
 */
class AthleteTemplate
{
    public const HEADER = ['No', '姓', '名', 'フリガナ', '学年', '性別', '所属陸協', '種別'];

    public const ROWS = 100;

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('選手登録');

        $sheet->setCellValue('A1', '選手登録シート（絆記録挑戦会）');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', '1行に1人ずつ入力し、「選手のまとめて登録」でこのファイルをアップロードしてください。学年・性別・所属陸協・種別はセルを選ぶと一覧から選べます。フリガナは姓と名の間にスペースを入れてください。');
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(36);

        $sheet->fromArray(self::HEADER, null, 'A4');
        $sheet->getStyle('A4:H4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('265C64');

        // 記入例（「例」の行は読み込まない）
        $sheet->fromArray(['例', '鈴木', '太郎', 'スズキ タロウ', '3', '男', '東京', '高校生'], null, 'A5');
        $sheet->getStyle('A5:H5')->getFont()->getColor()->setRGB('888888');

        $last = 5 + self::ROWS;
        for ($i = 1; $i <= self::ROWS; $i++) {
            $sheet->setCellValue('A'.(5 + $i), $i);
        }
        $sheet->getStyle("A4:H$last")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BBBBBB');
        $sheet->getStyle("E6:H$last")->getNumberFormat()->setFormatCode('@');

        $lists = [
            'E' => Athlete::GRADES,
            'F' => ['男', '女'],
            'G' => Athlete::ASSOCIATIONS,
            'H' => Athlete::CATEGORIES,
        ];
        foreach ($lists as $col => $values) {
            $validation = $sheet->getCell($col.'6')->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('一覧から選んでください')
                ->setError('一覧にある値を選んでください。')
                ->setFormula1('"'.implode(',', $values).'"');
            $sheet->setDataValidation("{$col}6:{$col}$last", $validation);
        }

        foreach (['A' => 6, 'B' => 12, 'C' => 12, 'D' => 20, 'E' => 8, 'F' => 8, 'G' => 12, 'H' => 10] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A6');

        return $book;
    }
}
