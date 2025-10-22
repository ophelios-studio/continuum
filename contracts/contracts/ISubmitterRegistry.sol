// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

interface ISubmitterRegistry {
    /// @return Return true if `who` is currently allowed to anchor evidence.
    function isRegistered(address who) external view returns (bool);
}