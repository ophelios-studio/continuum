import "dotenv/config";
import "@nomicfoundation/hardhat-ignition";

const PK = process.env.DEPLOYER_PK ? [process.env.DEPLOYER_PK] : [];

export default {
    solidity: {
        version: "0.8.28",
        settings: {
            optimizer: {
                enabled: true,
                runs: 200
            }
        }
    },
    networks: {
        sepolia: {
            url: process.env.SEPOLIA_RPC || "https://rpc.sepolia.org",
            type: "http",
            chainType: "l1",
            accounts: PK,
            chainId: 11155111
        }
    }
};