<?php namespace Controllers\Public;

use Controllers\Controller;
use Ens\EnsService;
use Models\Account\Services\ActorService;
use Models\Application\SiweVerifier;
use Models\Application\AvatarDownloader;
use Throwable;
use Zephyrus\Core\Configuration;
use Zephyrus\Core\Session;
use Zephyrus\Network\Response;
use Zephyrus\Network\Router\Get;
use Zephyrus\Network\Router\Post;
use Zephyrus\Network\Router\Root;
use Zephyrus\Security\Cryptography;

#[Root("/auth")]
class AuthController extends Controller
{
    #[Get("/siwe/nonce")]
    public function nonce(): Response
    {
        $nonce = $this->getRandomNonce();
        Session::set('siwe_nonce', $nonce);
        return $this->json(['nonce' => $nonce]);
    }

    #[Post("/siwe/verify")]
    public function verify(): Response
    {
        $siweMessage = $this->request->getParameter('message');
        $signature = $this->request->getParameter('signature');
        if (!$siweMessage || !$signature) {
            return $this->jsonError(400, 'Bad request');
        }

        $expectedNonce = Session::get('siwe_nonce');
        if (!$expectedNonce) {
            return $this->jsonError(401, 'Missing server nonce');
        }

        $verifier = new SiweVerifier(
            allowedDomains: [$this->request->getUrl()->getHost()],
            allowedOrigins: [$this->request->getUrl()->getBaseUrl()],
            allowedChainIds: [1, 11155111] // mainnet and Sepolia
        );
        try {
            $result = $verifier->verify($siweMessage, $signature, $expectedNonce);
            Session::remove('siwe_nonce');
            Session::set('wallet', $result['address']);
            Session::set('auth_at', time());
            $rpcUrl = Configuration::read('services')['infura']['eth_url'];
            $ens = new EnsService($rpcUrl);
            $name = $ens->resolveEnsName($result['address']);
            if ($name) {
                Session::set('ens_name', $name);
                $avatar = $ens->resolveAvatar($name);
                if ($avatar) {
                    $resultAvatar = new AvatarDownloader($avatar)->download(md5($avatar));
                    Session::set('ens_avatar', $resultAvatar);
                }
            }
            $account = new ActorService()->findByAddress($result['address']);
            Session::set('actor', $account?->getRawData() ?? null);
            return $this->json(['address' => $result['address']]);
        } catch (Throwable $e) {
            return $this->jsonError(401, $e->getMessage());
        }
    }

    /**
     * Generates a cryptographically secure random nonce (EIP-4361) string with a specified length. The nonce is
     * encoded using a URL-safe base64 variation.
     *
     * @param int $length The desired length of the nonce string. Must be greater than or equal to 8. Default is 16.
     * @return string A cryptographically secure random nonce string.
     */
    private function getRandomNonce(int $length = 16): string
    {
        $bytes = Cryptography::randomBytes($length);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
