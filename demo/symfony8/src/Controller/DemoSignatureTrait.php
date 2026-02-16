<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SignaturePageType;
use App\Model\SignaturePageModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers for Signature, AcroForm and Signing demo controllers.
 */
trait DemoSignatureTrait
{
    /**
     * Renders a signature demo page with the given options and configuration explanation.
     *
     * @param array<string, mixed> $signatureOptions Options passed to SignatureCoordinatesType
     */
    private function signaturePage(Request $request, string $pageTitle, array $signatureOptions, string $configExplanation): Response
    {
        $model = new SignaturePageModel();
        $form = $this->createForm(SignaturePageType::class, $model, [
            'signature_options' => $signatureOptions,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $model = $form->getData();
                if ($this->wantsJson($request)) {
                    $coords = $model->getSignatureCoordinates();
                    return new JsonResponse([
                        'success' => true,
                        'coordinates' => $this->formatCoordinates($coords),
                        'unit' => $coords->getUnit(),
                        'origin' => $coords->getOrigin(),
                    ]);
                }
                $coords = $model->getSignatureCoordinates();
                $this->addFlash('success', 'Coordinates saved (demo). ' . $this->formatCoordinatesForFlash($coords));
            }
            if (!$form->isValid()) {
                $this->addFlash('error', 'Please correct the errors in the form below.');
            }
        }

        return $this->render('signature/index.html.twig', [
            'form' => $form,
            'page_title' => $pageTitle,
            'config_explanation' => $configExplanation,
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    /**
     * @return array<int, array{name: string, page: int, x: float, y: float, width: float, height: float, angle: float}>
     */
    private function formatCoordinates(SignatureCoordinatesModel $model): array
    {
        $out = [];
        foreach ($model->getSignatureBoxes() as $box) {
            $out[] = [
                'name' => $box->getName(),
                'page' => $box->getPage(),
                'x' => $box->getX(),
                'y' => $box->getY(),
                'width' => $box->getWidth(),
                'height' => $box->getHeight(),
                'angle' => $box->getAngle(),
            ];
        }
        return $out;
    }

    private function formatCoordinatesForFlash(SignatureCoordinatesModel $model): string
    {
        $boxes = $this->formatCoordinates($model);
        $unit = $model->getUnit();
        $origin = $model->getOrigin();
        $intro = sprintf('Unit: %s, origin: %s.', $unit, $origin);
        if ($boxes === []) {
            return $intro . ' No boxes.';
        }
        $items = array_map(static function (array $b) use ($unit): string {
            $name = htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8');
            $angle = isset($b['angle']) ? (float) $b['angle'] : 0.0;
            return sprintf(
                '<li><strong>%s</strong>: page %d, x=%s, y=%s, %s×%s (%s), angle=%s°</li>',
                $name,
                $b['page'],
                (string) $b['x'],
                (string) $b['y'],
                (string) $b['width'],
                (string) $b['height'],
                $unit,
                (string) $angle
            );
        }, $boxes);
        return $intro . ' <ul class="mb-0 mt-1">' . implode('', $items) . '</ul>';
    }
}
