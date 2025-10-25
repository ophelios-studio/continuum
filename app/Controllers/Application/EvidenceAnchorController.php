<?php namespace Controllers\Application;

use Models\Account\Entities\Actor;
use Models\Legal\Services\EvidenceService;
use Zephyrus\Application\Flash;
use Zephyrus\Core\Configuration;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;

#[Root("/cases/{caseId}/evidences/{evidenceId}")]
class EvidenceAnchorController extends AppController
{
    #[Get("/anchor/prepare")]
    public function prepare(string $caseId, string $evidenceId): Response
    {
        $actor = Actor::build(Session::get('actor'));

        $svc = new EvidenceService();
        $evidence = $svc->findById($evidenceId);
        if (!$evidence || $evidence->case_id !== $caseId) {
            return $this->jsonError(404, "Evidence not found");
        }

        if ($evidence->status === 'ANCHORED') {
            return $this->jsonError(400, "Evidence already anchored");
        }

        $contentHash = new EvidenceService()->computeContentHash($evidenceId);
        $mediaUri = $evidence->media_uri ?? null;

        $cfg = Configuration::read('services')['web3'];
        $chainId  = $cfg['chain_id'] ?? 11155111;
        $registry = $cfg['evidence_registry_addr'] ?? '';
        if (!$registry) {
            return $this->jsonError(500, "EvidenceRegistry address missing");
        }

        return $this->json([
            'evidenceId' => $evidence->id,
            'caseId' => $evidence->case_id,
            'evidenceIdHex' => strtolower($evidence->evidence_id_hex),
            'jurisdiction' => $evidence->jurisdiction,
            'kind' => $evidence->kind,
            'contentHash' => strtolower($contentHash),
            'mediaUri' => $mediaUri,
            'chainId' => $chainId,
            'registry' => $registry
        ]);
    }

    #[Post("/anchor/confirm")]
    public function confirm(string $caseId, string $evidenceId): Response
    {
        $actor = Actor::build(Session::get('actor'));

        $txHash = $this->request->getParameter('txHash') ?? '';
        if (!preg_match('/^0x[0-9a-fA-F]{64}$/', $txHash)) {
            return $this->jsonError(400, "Invalid tx hash");
        }

        $svc = new EvidenceService();
        $e = $svc->findById($evidenceId);
        if (!$e || $e->case_id !== $caseId) {
            return $this->jsonError(404, "Evidence not found");
        }
        $contentHash = new EvidenceService()->computeContentHash($evidenceId);
        $svc->anchor(
            $evidenceId,
            $actor->address,
            $txHash,
            $contentHash
        );
        Flash::success("The evidence has been anchored successfully 🎉.");
        return $this->json(['ok' => true, 'txHash' => strtolower($txHash)]);
    }
}
