// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/metatx/ERC2771Context.sol";
import "./Roles.sol";

contract SubmitterRegistry is ERC2771Context, AccessControl, Pausable {
    using Roles for *;

    struct Submitter {
        Roles.RoleLevel level;
        bytes32 profileHash;
        string jurisdiction;
        uint64 verifiedUntil;
        uint256 orgId;
    }

    mapping(address => Submitter) public submitters;

    event SubmitterRegistered(address indexed wallet, bytes32 profileHash, string jurisdiction);
    event SubmitterVerified(address indexed wallet, Roles.RoleLevel level, uint64 verifiedUntil, uint256 orgId);
    event SubmitterRevoked(address indexed wallet);

    constructor(address admin, address trustedForwarder)
        ERC2771Context(trustedForwarder) {
        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(Roles.ADMIN, admin);
        _grantRole(Roles.VALIDATOR, admin);
    }

    function pause() external onlyRole(Roles.ADMIN) {
        _pause();
    }

    function unpause() external onlyRole(Roles.ADMIN) {
        _unpause();
    }

    function registerSubmitter(bytes32 profileHash, string calldata jurisdiction) external whenNotPaused {
        address sender = _msgSender();
        Submitter storage s = submitters[sender];
        require(s.level == Roles.RoleLevel.NONE || s.level == Roles.RoleLevel.REVOKED, "Already registered");
        s.level = Roles.RoleLevel.DECLARED;
        s.profileHash = profileHash;
        s.jurisdiction = jurisdiction;
        s.verifiedUntil = 0;
        s.orgId = 0;
        emit SubmitterRegistered(sender, profileHash, jurisdiction);
    }

    function verifySubmitter(
        address wallet,
        Roles.RoleLevel level,
        uint64 verifiedUntil,
        uint256 orgId
    ) external whenNotPaused onlyRole(Roles.VALIDATOR) {
        require(level == Roles.RoleLevel.VERIFIED_L1 || level == Roles.RoleLevel.VERIFIED_L2, "Invalid level");
        Submitter storage s = submitters[wallet];
        require(s.level != Roles.RoleLevel.NONE, "Not registered");
        s.level = level;
        s.verifiedUntil = verifiedUntil;
        s.orgId = orgId;
        emit SubmitterVerified(wallet, level, verifiedUntil, orgId);
    }

    function revokeSubmitter(address wallet) external whenNotPaused onlyRole(Roles.VALIDATOR) {
        Submitter storage s = submitters[wallet];
        s.level = Roles.RoleLevel.REVOKED;
        emit SubmitterRevoked(wallet);
    }

    function roleLevel(address wallet) external view returns (Roles.RoleLevel) {
        return submitters[wallet].level;
    }

    function _msgSender() internal view override(Context, ERC2771Context) returns (address) {
        return ERC2771Context._msgSender();
    }

    function _msgData() internal view override(Context, ERC2771Context) returns (bytes calldata) {
        return ERC2771Context._msgData();
    }

    function _contextSuffixLength() internal view override(Context, ERC2771Context) returns (uint256) {
        return ERC2771Context._contextSuffixLength();
    }
}