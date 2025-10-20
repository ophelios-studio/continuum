<?php namespace Models\Account\Brokers;

use kornrunner\Keccak;
use Models\Account\Entities\Actor;
use Models\Core\Broker;
use stdClass;
use Zephyrus\Security\Cryptography;

class ActorBroker extends Broker
{
    public function findByAddress(string $address): ?stdClass
    {
        $sql = "SELECT * FROM account.actor WHERE address = ?";
        return $this->selectSingle($sql, [$address]);
    }

    public function findByActivationCode(string $confirmationToken): ?stdClass
    {
        $sql = "SELECT * 
                  FROM account.actor 
                 WHERE verification_token = ?";
        $actor = $this->selectSingle($sql, [$confirmationToken]);
        if (is_null($actor)) {
            return null;
        }
        return $this->findByAddress($actor->address);
    }

    public function insert(stdClass $new, string $level = 'DECLARED'): string
    {
        $verificationToken = Cryptography::randomString(64);
        $new->profile_json = $this->canonicalJson($new);
        $new->profile_hash = '0x' . Keccak::hash($new->profile_json, 256);
        $sql = "INSERT INTO account.actor(address, firstname, lastname, email, primary_role, jurisdiction, profile_json, profile_hash, organization_id, verification_token, level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->query($sql, [
            $new->address,
            $new->firstname,
            $new->lastname,
            $new->email,
            $new->primary_role,
            $new->jurisdiction,
            $new->profile_json,
            $new->profile_hash,
            null,
            $verificationToken,
            $level
        ]);
        return $verificationToken;
    }

    public function updateEmail(string $address, string $newEmail): string
    {
        $verificationToken = Cryptography::randomString(64);
        $sql = "UPDATE account.actor 
                   SET email = ?, 
                       verification_token = ? 
                 WHERE address = ?";
        $this->query($sql, [$newEmail, $verificationToken, $address]);
        return $verificationToken;
    }

    public function emailExists(string $email): bool
    {
        $sql = "SELECT COUNT(address) as n FROM account.actor WHERE email = ?";
        return $this->selectSingle($sql, [$email])->n > 0;
    }

    public function activate(Actor $actor): void
    {
        $sql = "UPDATE account.actor 
                   SET verification_token = NULL
                 WHERE address = ?";
        $this->query($sql, [
            $actor->address
        ]);
    }

    private function canonicalJson(stdClass $new): string
    {
        $ordered = [
            'version' => 1,
            'address' => strtolower($new->address),
            'firstName' => $new->firstname,
            'lastName' => $new->lastname,
            'email' => $new->email,
            'jurisdiction' => $new->jurisdiction,
            'nonce' => bin2hex(random_bytes(16)),
            'issuedAt' => gmdate('c')
        ];
        return json_encode($ordered, JSON_UNESCAPED_SLASHES);
    }
}
