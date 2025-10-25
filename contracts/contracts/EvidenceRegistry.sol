// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";

import "./ISubmitterRegistry.sol";

/**
 * @title EvidenceRegistry
 * @notice On-chain anchor + custody timeline via events (lean storage).
 *         Works for digital and physical evidence. Cases remain off-chain.
 */
contract EvidenceRegistry is AccessControl, Pausable, ReentrancyGuard {
    bytes32 public constant ADMIN_ROLE = DEFAULT_ADMIN_ROLE;

    ISubmitterRegistry public immutable submitterRegistry;

    struct EvidenceState {
        bool exists;
        address submitter;
        bytes32 contentHash; // artifact/content hash (e.g., keccak256 of canonical JSON or file)
        address currentCustodian; // Current custodian (after acceptance); initially = submitter
        address pendingCustodian; // If a transfer is initiated but not yet accepted
        string jurisdiction; // Jurisdiction snapshot at anchor time
        string kind; // free-form kind/type, e.g., "MOBILE_DUMP", "PHOTO", "PHYSICAL_ITEM"
        string mediaURI; // content URI (e.g., ipfs://.. for JSON canon or metadata)
        uint64 anchoredAt;
    }

    // evidenceId => state
    mapping(bytes32 => EvidenceState) private _state;

    // EVENTS

    /// Emitted once per evidence anchor.
    event EvidenceAnchored(
        bytes32 indexed evidenceId,
        address indexed submitter,
        address indexed custodian,
        bytes32 contentHash,
        string caseRef,
        string jurisdiction,
        string kind,
        string mediaURI,
        uint64 anchoredAt
    );

    /// Emitted when current custodian proposes a transfer.
    event CustodyTransferInitiated(
        bytes32 indexed evidenceId,
        address indexed from,
        address indexed to,
        string purpose,
        uint64 expectedReturnAt, // 0 if N/A
        bytes32 offchainContextHash // e.g., IPFS hash of a richer transfer note
    );

    /// Emitted when the pending custodian accepts the transfer.
    event CustodyAccepted(
        bytes32 indexed evidenceId,
        address indexed from,
        address indexed to,
        uint64 acceptedAt
    );

    /// Emitted when the current custodian returns the evidence to a specific address (e.g., back to previous).
    event CustodyReturned(
        bytes32 indexed evidenceId,
        address indexed from,
        address indexed to,
        string note,
        uint64 returnedAt
    );

    constructor(address admin, ISubmitterRegistry registry) {
        require(address(registry) != address(0), "registry required");
        _grantRole(ADMIN_ROLE, admin);
        submitterRegistry = registry;
    }

    /**
     * @notice Validates if the given address is a registered submitter
     * @dev Checks if the address has a valid role level (DECLARED, VERIFIED_L1, or VERIFIED_L2)
     * @param who The address to validate
     * @custom:throws "submitter not registered" if the address is not a registered submitter
     */
    function _requireRegisteredSubmitter(address who) internal view {
        Roles.RoleLevel lvl = submitterRegistry.roleLevel(who);
        require(
            lvl == Roles.RoleLevel.DECLARED ||
            lvl == Roles.RoleLevel.VERIFIED_L1 ||
            lvl == Roles.RoleLevel.VERIFIED_L2,
            "submitter not registered"
        );
    }

    function stateOf(bytes32 evidenceId) external view returns (EvidenceState memory s) {
        s = _state[evidenceId];
        require(s.exists, "evidence not found");
    }

    function pause() external onlyRole(ADMIN_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(ADMIN_ROLE) {
        _unpause();
    }

    // LIFECYCLE

    /**
     * @notice Anchor a new piece of evidence.
     * @param evidenceId Client-chosen id (recommended: keccak256 of your canonical JSON / artifact hash + salt)
     * @param contentHash Keccak-256 of the canonical artifact / metadata JSON
     * @param caseRef off-chain case reference (e.g., "C-2025-000012")
     * @param jurisdiction Optional snapshot (e.g., "CA-QC")
     * @param kind Optional category ("PHOTO", "MOBILE_DUMP", "PHYSICAL_ITEM", ...)
     * @param mediaURI Optional pointer (e.g., ipfs://... to a JSON canon or descriptor)
     */
    function anchorEvidence(
        bytes32 evidenceId,
        bytes32 contentHash,
        string calldata caseRef,
        string calldata jurisdiction,
        string calldata kind,
        string calldata mediaURI
    ) external whenNotPaused nonReentrant {
        _requireRegisteredSubmitter(msg.sender);
        require(evidenceId != bytes32(0), "invalid id");
        require(!_state[evidenceId].exists, "evidence exists");

        EvidenceState storage s = _state[evidenceId];
        s.exists = true;
        s.submitter = msg.sender;
        s.contentHash = contentHash;
        s.currentCustodian = msg.sender;
        s.jurisdiction = jurisdiction;
        s.kind = kind;
        s.mediaURI = mediaURI;
        s.anchoredAt = uint64(block.timestamp);

        emit EvidenceAnchored(
            evidenceId,
            msg.sender,
            msg.sender,
            contentHash,
            caseRef,
            jurisdiction,
            kind,
            mediaURI,
            uint64(block.timestamp)
        );
    }

    function currentCustodian(bytes32 evidenceId) external view returns (address) {
        EvidenceState storage s = _state[evidenceId];
        require(s.exists, "evidence not found");
        return s.currentCustodian;
    }

    /**
     * @notice Initiate a transfer to `to`. Requires current custodian. The transfer becomes effective only after `to`
     * calls `acceptCustody`.
     */
    function initiateTransfer(
        bytes32 evidenceId,
        address to,
        string calldata purpose,
        uint64 expectedReturnAt,
        bytes32 offchainContextHash
    ) external whenNotPaused nonReentrant {
        EvidenceState storage s = _state[evidenceId];
        require(s.exists, "evidence not found");
        require(msg.sender == s.currentCustodian, "only custodian");
        require(to != address(0) && to != s.currentCustodian, "bad recipient");

        s.pendingCustodian = to;

        emit CustodyTransferInitiated(
            evidenceId,
            s.currentCustodian,
            to,
            purpose,
            expectedReturnAt,
            offchainContextHash
        );
    }

    /**
     * @notice Accept a pending transfer. Caller must match `pendingCustodian`.
     */
    function acceptCustody(bytes32 evidenceId) external whenNotPaused nonReentrant {
        EvidenceState storage s = _state[evidenceId];
        require(s.exists, "evidence not found");
        require(s.pendingCustodian != address(0), "no pending transfer");
        address from = s.currentCustodian;
        address to = s.pendingCustodian;
        require(msg.sender == to, "only pending custodian");

        s.currentCustodian = to;
        s.pendingCustodian = address(0);

        emit CustodyAccepted(evidenceId, from, to, uint64(block.timestamp));
    }

    /**
     * @notice Return evidence from current custodian to `to` (e.g., the prior custodian).
     *         This is a one-step action by the current custodian (no accept needed).
     */
    function returnCustody(bytes32 evidenceId, address to, string calldata note) external whenNotPaused nonReentrant {
        EvidenceState storage s = _state[evidenceId];
        require(s.exists, "evidence not found");
        require(msg.sender == s.currentCustodian, "only custodian");
        require(to != address(0) && to != s.currentCustodian, "bad recipient");

        address from = s.currentCustodian;
        s.currentCustodian = to;
        s.pendingCustodian = address(0);

        emit CustodyReturned(evidenceId, from, to, note, uint64(block.timestamp));
    }
}