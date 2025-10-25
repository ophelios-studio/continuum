<?php namespace Models\Legal\Services;

use Models\Account\Entities\Actor;
use Models\Legal\Brokers\CaseEventBroker;
use Models\Legal\Brokers\CaseParticipantBroker;
use Models\Legal\Brokers\LegalCaseBroker;
use Models\Legal\Entities\CaseEvent;
use Models\Legal\Entities\CaseParticipant;
use Models\Legal\Entities\LegalCase;
use Models\Legal\Validators\LegalCaseValidator;
use Models\Legal\Validators\MemberValidator;
use Zephyrus\Application\Form;

final readonly class LegalCaseService
{
    public function __construct(
        private LegalCaseBroker $cases = new LegalCaseBroker(),
        private CaseParticipantBroker $participants = new CaseParticipantBroker(),
        private CaseEventBroker $events = new CaseEventBroker()
    ) {}

    public function findById(string $id): ?LegalCase
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
        $this->participants->add($id, (object) [
            'address' => $case->created_by,
            'role' => 'OWNER',
            'org_id' => $case->organization_id
        ]);
        $this->events->add($id, $case->created_by, 'CASE_CREATED', [
            'title' => $case->title,
            'jurisdiction' => $case->jurisdiction,
        ]);
        return $case;
    }

    public function isParticipant(string $caseId, string $address): bool
    {
        return $this->participants->isParticipant($caseId, $address);
    }

    public function findAllParticipants(string $caseId): array
    {
        return CaseParticipant::buildArray($this->participants->list($caseId));
    }

    public function findAllEvents(string $caseId, int $limit = 100, int $offset = 0): array
    {
        return CaseEvent::buildArray($this->events->list($caseId, $limit, $offset));
    }

    public function addMember(string $caseId, Actor $owner, Form $form): void
    {
        MemberValidator::assertInsert($caseId, $form);
        $this->participants->add($caseId, $form->buildObject());
        $this->events->add($caseId, $owner->address, 'MEMBER_ADDED', ['address' => $form->getValue('address'), 'role' => $form->getValue('role')]);
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
