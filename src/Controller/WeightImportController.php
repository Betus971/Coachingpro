<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\BoditraxCsvImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/poids/import', name: 'app_weight_import', methods: ['POST'])]
class WeightImportController extends AbstractController
{
    public function __invoke(Request $request, BoditraxCsvImporter $importer): Response
    {
        $file = $request->files->get('csv_file');

        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Fichier invalide ou absent.');
            return $this->redirectToRoute('app_weight_index');
        }

        $allowed = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        if (!in_array($file->getMimeType(), $allowed, true)
            && !str_ends_with(strtolower($file->getClientOriginalName()), '.csv')) {
            $this->addFlash('error', 'Le fichier doit être un CSV (.csv).');
            return $this->redirectToRoute('app_weight_index');
        }

        if ($file->getSize() > 2 * 1024 * 1024) { // 2 Mo max
            $this->addFlash('error', 'Fichier trop volumineux (max 2 Mo).');
            return $this->redirectToRoute('app_weight_index');
        }

        /** @var User $user */
        $user    = $this->getUser();
        $content = file_get_contents($file->getPathname());

        $result = $importer->import($content, $user);

        if ($result['imported'] > 0) {
            $this->addFlash('success',
                "✓ {$result['imported']} mesure(s) importée(s)"
                . ($result['skipped'] > 0 ? ", {$result['skipped']} ignorée(s)" : '')
                . '.'
            );
        } else {
            $this->addFlash('error', 'Aucune mesure importée. '.(implode(' ', $result['errors']) ?: 'Vérifiez le fichier.'));
        }

        foreach (array_slice($result['errors'], 0, 5) as $err) {
            $this->addFlash('error', $err);
        }

        return $this->redirectToRoute('app_weight_index');
    }
}
