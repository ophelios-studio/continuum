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


