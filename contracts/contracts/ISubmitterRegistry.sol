// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "./Roles.sol";

interface ISubmitterRegistry {
    /// Returns the current role level for a wallet.
    function roleLevel(address wallet) external view returns (Roles.RoleLevel);
}