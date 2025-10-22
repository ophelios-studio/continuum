<?php namespace Models\Legal\Services;

use Models\Account\Entities\Actor;
use Models\Legal\Brokers\CaseEventBroker;
use Models\Legal\Brokers\CaseParticipantBroker;
use Models\Legal\Brokers\LegalCaseBroker;
use Models\Legal\Entities\LegalCase;
use Models\Legal\Validators\LegalCaseValidator;
use Zephyrus\Application\Form;

final readonly class LegalCaseService
{
    public function __construct(
        private LegalCaseBroker $cases = new LegalCaseBroker(),
        private CaseParticipantBroker $participants = new CaseParticipantBroker(),
        private CaseEventBroker $events = new CaseEventBroker()
    ) {}

    public function findById(string $id): LegalCase
    {
        return LegalCase::build($this->cases->findById($id));
    }

    /**
     * @param string $wallet
     * @param int|null $orgId
     * @param string|null $status
     * @param string|null $search
     * @return array<LegalCase>
     */
    public function findAllForWallet(string $wallet, ?int $orgId = null, ?string $status = null, ?string $search = null): array
    {
        return LegalCase::buildArray($this->cases->listForWallet($wallet, $orgId, $status, $search));;
    }

    public function createCase(Form $form, Actor $creator): LegalCase
    {
        //LegalCaseValidator::assertInsert($form);
        $id = $this->cases->insert($form->buildObject(), $creator);
        $case = $this->findById($id);
        $this->participants->add($id, $case->created_by, 'OWNER', $case->organization_id);
        $this->events->add($id, $case->created_by, 'CASE_CREATED', [
            'title' => $case->title,
            'jurisdiction' => $case->jurisdiction,
        ]);
        return $case;
    }

    public function addMember(int $caseId, string $owner, string $address, string $role, ?int $orgId = null): void
    {
        $this->participants->add($caseId, $address, $role, $orgId, true);
        $this->events->add($caseId, $owner, 'MEMBER_ADDED', ['address' => $address, 'role' => $role]);
    }

    public function changeMemberRole(int $caseId, string $owner, string $address, string $role): void
    {
        $this->participants->changeRole($caseId, $address, $role);
        $this->events->add($caseId, $owner, 'MEMBER_ROLE_CHANGED', ['address' => $address, 'role' => $role]);
    }

    public function removeMember(int $caseId, string $owner, string $address): void
    {
        $this->participants->remove($caseId, $address);
        $this->events->add($caseId, $owner, 'MEMBER_REMOVED', ['address' => $address]);
    }
}
