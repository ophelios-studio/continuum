import {
    createWalletClient,
    custom,
    encodeFunctionData,
    parseAbi,
} from "https://esm.sh/viem@2.38.3";
import { ensureChain, getProvider, getAccount, estimateGas } from "./wallet.js";

async function anchorEvidence(prepareUrl, confirmUrl, {onStatus = msg => {console.log(msg)}}) {

    onStatus("Preparing payload...");
    const prepRes = await fetch(prepareUrl, { credentials: "include" });
    if (!prepRes.ok) throw new Error((await prepRes.text()) || "Prepare failed");
    const response = await prepRes.json();
    console.log(response);
    const chainId = parseInt(response.chainId);

    const provider = getProvider();
    onStatus("Connecting wallet ...");
    const account = await getAccount(provider);
    console.log("Account:", account);
    await ensureChain(provider, chainId);

    const client = createWalletClient({
        chain: { id: chainId, name: "Custom", nativeCurrency: { name: "ETH", symbol: "ETH", decimals: 18 } },
        transport: custom(provider),
    });

    const abi = parseAbi([
        "function anchorEvidence(bytes32 evidenceId, bytes32 contentHash, string calldata caseRef, string calldata jurisdiction, string calldata kind, string calldata mediaURI)"
    ]);
    const data = encodeFunctionData({
        abi,
        functionName: "anchorEvidence",
        args: [
            response.evidenceIdHex,
            response.contentHash,
            response.caseId,
            response.jurisdiction,
            response.kind,
            response.mediaUri
        ]
    });

    onStatus("Estimating gas...");
    const gas = await estimateGas(provider, account, response.registry, data);
    console.log("Estimated gas:", gas);

    onStatus("Sending transaction...");
    const txHash = await client.sendTransaction({
        account,
        to: response.registry,
        data,
        gas
    });
    console.log("Transaction sent:", txHash);

    onStatus("Anchored on-chain. Recording locally...");
    const conf = await fetch(confirmUrl, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ txHash }),
    });
    if (!conf.ok) {
        throw new Error((await conf.text()) || "Confirm failed");
    } else {
        console.log(await conf.json());
    }

    onStatus("Done");
    return { txHash };
}

export function initAnchorButton(btn) {
    const statusEl = document.getElementById("anchorStatus");
    const setStatus = (m) => { if (statusEl) statusEl.textContent = m || ""; };
    if (!btn) return;
    btn.addEventListener("click", async () => {
        try {
            btn.disabled = true;
            const prepareUrl = btn.dataset.prepareUrl;
            const confirmUrl = btn.dataset.confirmUrl;
            const { txHash } = await anchorEvidence(prepareUrl, confirmUrl, setStatus);
            setStatus(`Anchored Tx: ${txHash}`);
            setTimeout(function () {
                window.location.reload();
            }, 2000);
        } catch (err) {
            console.error(err);
            setStatus(err?.message || "Failed to anchor.");
            btn.disabled = false;
        }
    });
}