<?php

declare(strict_types=1);

namespace Bites\Base\Actions;

class GeneratePdf
{
    public function execute(string $view, array $data = []): string
    {
        // Example: using barryvdh/laravel-dompdf
        $pdf = app('dompdf.wrapper')
            ->loadView($view, $data);

        $fileName = storage_path('app/tmp/'.uniqid().'.pdf');

        $pdf->save($fileName);

        return $fileName;
    }
}
