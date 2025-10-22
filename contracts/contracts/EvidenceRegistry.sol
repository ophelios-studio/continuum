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

    constructor(address admin, ISubmitterRegistry registry) {
        require(address(registry) != address(0), "registry required");
        _grantRole(ADMIN_ROLE, admin);
        submitterRegistry = registry;
    }

    function _requireRegisteredSubmitter(address who) internal view {
        require(submitterRegistry.isRegistered(who), "submitter not registered");
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
}