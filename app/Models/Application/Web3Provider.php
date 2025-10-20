<?php namespace Models\Application;

use Web3\Web3;
use Web3\Providers\HttpProvider;
use Zephyrus\Core\Configuration;

class Web3Provider
{
    private static ?Web3Provider $instance = null;
    private Web3 $web3;

    public static function getInstance(): Web3Provider
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function abi(string $file): array
    {
        $p = ROOT_DIR . "/abi/$file";
        return json_decode(file_get_contents($p), true);
    }

    public function getWeb3(): Web3
    {
        return $this->web3;
    }

    private function __construct()
    {
        $rpc = Configuration::read('services')['infura']['eth_url'];
        $this->web3 = new Web3(new HttpProvider($rpc, 30));
    }
}
