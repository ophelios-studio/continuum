// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract MinimalForwarder {
    struct ForwardRequest {
        address from;
        address to;
        uint256 value;
        uint256 gas;
        uint256 nonce;
        bytes data;
    }

    bytes32 private constant TYPEHASH =
    keccak256(
        "ForwardRequest(address from,address to,uint256 value,uint256 gas,uint256 nonce,bytes data)"
    );

    mapping(address => uint256) private _nonces;

    // EIP-712 domain separator (chain-id dependent)
    bytes32 private immutable _DOMAIN_SEPARATOR;
    uint256 private immutable _CACHED_CHAIN_ID;
    bytes32 private constant _EIP712_DOMAIN_TYPEHASH =
    keccak256(
        "EIP712Domain(string name,string version,uint256 chainId,address verifyingContract)"
    );
    bytes32 private constant _NAME_HASH = keccak256("MinimalForwarder");
    bytes32 private constant _VERSION_HASH = keccak256("0.0.1");

    event Executed(address indexed from, address indexed to, bool success, bytes returndata);

    constructor() {
        _CACHED_CHAIN_ID = block.chainid;
        _DOMAIN_SEPARATOR = _buildDomainSeparator(_CACHED_CHAIN_ID);
    }

    function getNonce(address from) external view returns (uint256) {
        return _nonces[from];
    }

    function verify(ForwardRequest calldata req, bytes calldata signature) public view returns (bool) {
        address signer = _recoverSigner(req, signature);
        return _nonces[req.from] == req.nonce && signer == req.from;
    }

    function execute(ForwardRequest calldata req, bytes calldata signature)
    external
    payable
    returns (bool success, bytes memory returndata)
    {
        require(verify(req, signature), "MinimalForwarder: signature does not match request");
        _nonces[req.from] = req.nonce + 1;

        // Append the sender to the calldata to support ERC2771-style context in recipient
        bytes memory dataWithSender = bytes.concat(req.data, bytes20(req.from));

        // solhint-disable-next-line avoid-low-level-calls
        (success, returndata) = req.to.call{gas: req.gas, value: req.value}(dataWithSender);

        // If the call used all the gas, rethrow to avoid silent out-of-gas
        // See OZ’s approach: if success is false and returndata is empty, bubble up
        if (!success) {
            // If there is return data, bubble it up
            if (returndata.length > 0) {
                assembly {
                    revert(add(returndata, 32), mload(returndata))
                }
            } else {
                revert("MinimalForwarder: call failed");
            }
        }

        emit Executed(req.from, req.to, success, returndata);
    }

    // ============ Internal helpers ============

    function _buildDomainSeparator(uint256 chainId) private view returns (bytes32) {
        return keccak256(
            abi.encode(
                _EIP712_DOMAIN_TYPEHASH,
                _NAME_HASH,
                _VERSION_HASH,
                chainId,
                address(this)
            )
        );
    }

    function _domainSeparatorV4() private view returns (bytes32) {
        if (block.chainid == _CACHED_CHAIN_ID) {
            return _DOMAIN_SEPARATOR;
        }
        return _buildDomainSeparator(block.chainid);
    }

    function _recoverSigner(ForwardRequest calldata req, bytes calldata sig) private view returns (address) {
        bytes32 structHash = keccak256(
            abi.encode(
                TYPEHASH,
                req.from,
                req.to,
                req.value,
                req.gas,
                req.nonce,
                keccak256(req.data)
            )
        );
        bytes32 digest = keccak256(abi.encodePacked("\x19\x01", _domainSeparatorV4(), structHash));
        return _ecdsaRecover(digest, sig);
    }

    function _ecdsaRecover(bytes32 digest, bytes calldata sig) private pure returns (address) {
        if (sig.length != 65) revert("MinimalForwarder: bad signature length");
        uint8 v;
        bytes32 r;
        bytes32 s;
        // sig = r(32) | s(32) | v(1)
        assembly {
            r := calldataload(sig.offset)
            s := calldataload(add(sig.offset, 32))
            v := byte(0, calldataload(add(sig.offset, 64)))
        }
        if (v < 27) v += 27;
        // EIP-2 malleability check
        // secp256k1n/2
        bytes32 maxS = 0x7fffffffffffffffffffffffffffffff5d576e7357a4501ddfe92f46681b20a0;
        if (uint256(s) > uint256(maxS)) revert("MinimalForwarder: invalid s");
        if (v != 27 && v != 28) revert("MinimalForwarder: invalid v");
        address signer = ecrecover(digest, v, r, s);
        require(signer != address(0), "MinimalForwarder: ecrecover zero");
        return signer;
    }
}