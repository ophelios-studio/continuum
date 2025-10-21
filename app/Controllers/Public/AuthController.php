<?php namespace Controllers\Public;

use Controllers\Controller;
use Ens\EnsService;
use Models\Account\Services\ActorService;
use Models\Application\SiweVerifier;
use Models\Application\AvatarDownloader;
use Throwable;
use Usarise\Identicon\Identicon;
use Usarise\Identicon\Image\Svg\Canvas as SvgCanvas;
use Zephyrus\Application\Flash;
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
            $basedir = ROOT_DIR . '/public/assets/images/avatars';
            $resultAvatar = $this->findFileByBasename($basedir, md5($result['address']));
            $expired = $resultAvatar && filemtime($basedir . "/" . $resultAvatar) < time() - 86400; // 24h
            if ($name) {
                Session::set('ens_name', $name);
                if (!$resultAvatar || $expired) {
                    $avatar = $ens->resolveAvatar($name);
                    if ($avatar) {
                        $resultAvatar = new AvatarDownloader($avatar)->download(md5($result['address']));
                    } else {
                        $identicon = new Identicon(new SvgCanvas(), 420);
                        $response = $identicon->generate($result['address']);
                        $resultAvatar = md5($result['address']) . "." . $response->format;
                        $response->save($basedir . '/' . $resultAvatar);
                    }
                }
            } else {
                if (!$resultAvatar || $expired) {
                    $identicon = new Identicon(new SvgCanvas(), 420);
                    $response = $identicon->generate($result['address']);
                    $resultAvatar = md5($result['address']) . "." . $response->format;
                    $response->save($basedir . '/' . $resultAvatar);
                }
            }
            Session::set('ens_avatar', $resultAvatar);
            $account = new ActorService()->findByAddress($result['address']);
            Session::set('actor', null);
            if ($account) {
                Session::set('actor', $account->getRawData());
            }
            return $this->json(['address' => $result['address']]);
        } catch (Throwable $e) {
            return $this->jsonError(401, $e->getMessage());
        }
    }

    #[Post("/siwe/anchor")]
    public function anchor(): Response
    {
        $wallet = Session::get('wallet');
        if (!$wallet) {
            return $this->jsonError(401, 'SIWE required');
        }

        $tx = $this->request->getParameter('txHash');
        if (!$tx || !preg_match('/^0x[0-9a-fA-F]{64}$/', $tx)) {
            return $this->jsonError(400, 'Invalid tx hash');
        }

        $service = new ActorService();
        $service->anchor($wallet, $tx);
        Session::set("actor", $service->findByAddress($wallet)->getRawData());
        Flash::success("Account successfully anchored 🎉! Transaction: $tx.");
        return $this->json(['ok' => true]);
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

    /**
     * Find a file by base name regardless of extension.
     *
     * @param string $dir       Directory to search
     * @param string $basename  Name without extension (e.g., "avatar" or "user.profile")
     * @return string|null Filename with extension (e.g., "avatar.png") or null if not found
     */
    private function findFileByBasename(string $dir, string $basename): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        $quoted = preg_quote($basename, '/');
        $pattern = '/^' . $quoted . '\.[^.]+$/i'; // "<basename>.<ext>" where ext has no dots

        $dh = opendir($dir);
        if ($dh === false) {
            return null;
        }

        $result = null;
        while (($entry = readdir($dh)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($full)) {
                continue;
            }
            if (preg_match($pattern, $entry) === 1) {
                $result = $entry; // return filename with extension only
                break;
            }
        }
        closedir($dh);
        return $result;
    }
}
