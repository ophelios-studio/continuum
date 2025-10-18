<?php namespace Models\Account\Brokers;

use Models\Core\Broker;
use stdClass;
use Zephyrus\Security\Cryptography;

class SubmitterBroker extends Broker
{
    public function findByAddress(string $address): ?stdClass
    {
        $sql = "SELECT * FROM account.submitter WHERE address = ?";
        return $this->selectSingle($sql, [$address]);
    }

    public function insert(stdClass $new): string
    {
        $verificationToken = Cryptography::randomString(64);
        $sql = "INSERT INTO account.submitter(address, firstname, lastname, email, jurisdiction, profile_hash, organization_id, verification_token) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $this->query($sql, [
            $new->address,
            $new->firstname,
            $new->lastname,
            $new->email,
            $new->jurisdiction,
            $this->canonicalJson($new),
            null,
            $verificationToken
        ]);
        return $verificationToken;
    }

    public function updateEmail(string $address, string $newEmail): string
    {
        $verificationToken = Cryptography::randomString(64);
        $sql = "UPDATE account.submitter 
                   SET email = ?, 
                       verification_token = ? 
                 WHERE address = ?";
        $this->query($sql, [$newEmail, $verificationToken, $address]);
        return $verificationToken;
    }

    public function emailExists(string $email): bool
    {
        $sql = "SELECT COUNT(address) as n FROM account.submitter WHERE email = ?";
        return $this->selectSingle($sql, [$email])->n > 0;
    }

    private function canonicalJson(stdClass $new): string
    {
        $ordered = [
            'version' => 1,
            'address' => strtolower($new->address),
            'firstName' => $new->firstName,
            'lastName' => $new->lastName,
            'email' => $new->email,
            'jurisdiction' => $new->jurisdiction,
            'nonce' => bin2hex(random_bytes(16)),
            'issuedAt' => gmdate('c')
        ];
        return json_encode($ordered, JSON_UNESCAPED_SLASHES);
    }
}
