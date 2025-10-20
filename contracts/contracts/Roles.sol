// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

library Roles {
    bytes32 public constant ADMIN = keccak256("ADMIN");

    enum RoleLevel { NONE, DECLARED, VERIFIED_L1, VERIFIED_L2, REVOKED }
}