// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

library Roles {
    bytes32 public constant ADMIN = keccak256("ADMIN");
    bytes32 public constant VALIDATOR = keccak256("VALIDATOR");
    bytes32 public constant ORG_ADMIN = keccak256("ORG_ADMIN");
    bytes32 public constant UPGRADER = keccak256("UPGRADER");

    enum RoleLevel { NONE, DECLARED, VERIFIED_L1, VERIFIED_L2, REVOKED }
    enum EvidenceKind { DIGITAL, PHYSICAL }
    enum TrustLevel { DECLARED, L1, L2, REVOKED }
    enum Policy { NONE, AUTO_EXPIRE, AUTO_REASSIGN }
}