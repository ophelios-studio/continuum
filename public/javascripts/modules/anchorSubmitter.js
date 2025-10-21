import {
    createWalletClient,
    custom,
    encodeFunctionData,
    parseAbi,
} from "https://esm.sh/viem@2.38.3";

async function ensureChain(provider, targetChainId) {
    const current = await provider.request({ method: "eth_chainId" });
    const currentDec = parseInt(current, 16);
    if (currentDec === targetChainId) return;
    try {
        await provider.request({
            method: "wallet_switchEthereumChain",
            params: [{ chainId: "0x" + targetChainId.toString(16) }],
        });
    } catch (err) {
        if (err?.code === 4902) {
            throw new Error("Please add Sepolia (chainId 11155111) to your wallet and retry.");
        }
        throw new Error("Network switch was rejected. Please switch to Sepolia.");
    }
}

function hexToBigInt(hex) { return BigInt(hex); }
function toHex(bi) { return "0x" + bi.toString(16); }

export async function anchorProfile({submitterAddress, profileHash, jurisdiction, chainId = 11155111, onStatus = () => {}}) {
    const provider = window.ethereum;
    if (!provider) throw new Error("No wallet detected. Install MetaMask or a compatible wallet.");

    onStatus("Connecting wallet…");
    const [account] = await provider.request({ method: "eth_requestAccounts" });
    if (!account) throw new Error("No account connected.");

    await ensureChain(provider, chainId);

    const client = createWalletClient({
        chain: { id: chainId, name: "Custom", nativeCurrency: { name: "ETH", symbol: "ETH", decimals: 18 } },
        transport: custom(provider),
    });

    const abi = parseAbi([
        "function registerSubmitter(bytes32 profileHash, string jurisdiction)"
    ]);

    if (!/^0x[0-9a-fA-F]{40}$/.test(submitterAddress)) throw new Error("Invalid SubmitterRegistry address.");
    if (!/^0x[0-9a-fA-F]{64}$/.test(profileHash)) throw new Error("Invalid profile hash (0x + 64 hex).");
    if (typeof jurisdiction !== "string" || !jurisdiction.length) throw new Error("Jurisdiction required.");

    const data = encodeFunctionData({
        abi,
        functionName: "registerSubmitter",
        args: [profileHash, jurisdiction],
    });

    onStatus("Estimating gas ...");
    const latestBlock = await provider.request({
        method: "eth_getBlockByNumber",
        params: ["latest", false],
    });
    const cap = latestBlock?.gasLimit ? hexToBigInt(latestBlock.gasLimit) : BigInt(16_000_000); // fallback cap

    // estimate for this call
    let est;
    try {
        const estHex = await provider.request({
            method: "eth_estimateGas",
            params: [{ from: account, to: submitterAddress, data }],
        });
        est = hexToBigInt(estHex);
    } catch (e) {
        // fallback if estimation fails for any reason
        est = BigInt(300_000);
    }

    const safety = (est * BigInt(125)) / BigInt(100); // +25%
    const buffer = BigInt(50_000);
    const maxAllowed = cap > buffer ? (cap - buffer) : cap;
    let gasFinal = safety;
    if (gasFinal > maxAllowed) gasFinal = maxAllowed;
    if (gasFinal < BigInt(150_000)) gasFinal = BigInt(150_000); // minimum sensible gas for this function

    onStatus("Sending transaction…");
    const txHash = await client.sendTransaction({
        account,
        to: submitterAddress,
        data,
        gas: toHex(gasFinal),
    });

    onStatus("Anchored on-chain. Recording locally…");
    await fetch("/auth/siwe/anchor", {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ txHash }),
    });

    onStatus("Done");
    return { txHash };
}

export function initAnchorButton(btn, { chainId = 11155111, onDone } = {}) {
    const statusEl = document.getElementById("anchorStatus");
    const setStatus = (m) => { if (statusEl) statusEl.textContent = m || ""; };

    if (!btn) return;
    btn.addEventListener("click", async () => {
        try {
            btn.disabled = true;
            const submitterAddress = btn.dataset.submitContract;
            const profileHash      = btn.dataset.profileHash;
            const jurisdiction     = btn.dataset.jurisdiction;

            const { txHash } = await anchorProfile({
                submitterAddress,
                profileHash,
                jurisdiction,
                chainId,
                onStatus: setStatus,
            });

            setStatus(`Anchored Tx: ${txHash}`);
            if (typeof onDone === "function") {
                onDone(txHash);
            }
        } catch (err) {
            console.error(err);
            setStatus(err?.message || "Failed to anchor.");
            btn.disabled = false;
        }
    });
}