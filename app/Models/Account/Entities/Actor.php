<?php namespace Models\Account\Entities;

use Zephyrus\Core\Entity\Entity;

class Actor extends Entity
{
    public string $address;
    public string $level;
    public string $firstname;
    public string $lastname;
    public string $email;
    private string $primary_role;
    public string $jurisdiction;
    public string $profile_hash;
    public ?int $organization_id;
    public ?string $verification_token;
    public string $created_at;
    public string $updated_at;
}
