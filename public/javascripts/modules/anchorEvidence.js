import {
    createPublicClient,
    createWalletClient,
    custom,
    encodeFunctionData,
    parseAbi
} from 'https://esm.sh/viem@2.38.3';

export async function anchorEvidence({prepareUrl, confirmUrl, onStatus = () => {}}) {
    if (!window.ethereum) throw new Error('No wallet provider');

    onStatus('Preparing payload...');
    const prepRes = await fetch(prepareUrl, { credentials: 'include' });
    if (!prepRes.ok) throw new Error(await prepRes.text() || 'prepare failed');
    const { chainId, registry, functionName, evidenceIdHex, contentHash, mediaUri } = await prepRes.json();

    const publicClient = createPublicClient({
        chain: { id: chainId, name: 'custom', nativeCurrency: { name: 'ETH', symbol: 'ETH', decimals: 18 }, rpcUrls: { default: { http: [] } } },
        transport: custom(window.ethereum) // use wallet RPC for estimate
    });
    const walletClient = createWalletClient({
        chain: { id: chainId, name: 'custom', nativeCurrency: { name: 'ETH', symbol: 'ETH', decimals: 18 }, rpcUrls: { default: { http: [] } } },
        transport: custom(window.ethereum)
    });
    const [account] = await walletClient.getAddresses();
    if (!account) throw new Error('No connected account');

    const abi = parseAbi([
        `function ${functionName}(bytes32 evidenceId, bytes32 contentHash, string mediaUri)`
    ]);
    const data = encodeFunctionData({
        abi,
        functionName,
        args: [evidenceIdHex, contentHash, mediaUri ?? ""]
    });

    onStatus('Estimating gas...');
    let gas;
    try {
        gas = await publicClient.estimateGas({
            account,
            to: registry,
            data
        });
    } catch {
        gas = 800000n;
    }
    const gasCap = 1_500_000n;
    if (gas > gasCap) gas = gasCap;

    onStatus('Sending transaction...');
    const txHash = await walletClient.sendTransaction({
        account,
        to: registry,
        data,
        gas
    });

    onStatus('Waiting for confirmation...');
    await publicClient.waitForTransactionReceipt({ hash: txHash });

    const conf = await fetch(confirmUrl, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ txHash })
    });
    if (!conf.ok) throw new Error(await conf.text() || 'confirm failed');

    onStatus(`Anchored ${txHash}`);
    return { txHash };
}