// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

library Roles {
    bytes32 public constant ADMIN = keccak256("ADMIN");
    bytes32 public constant VALIDATOR = keccak256("VALIDATOR");
}