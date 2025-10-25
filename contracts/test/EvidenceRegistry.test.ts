import { expect } from "chai";
import { network } from "hardhat";
import { anyValue } from "@nomicfoundation/hardhat-ethers-chai-matchers/withArgs";
import type { EvidenceRegistry, SubmitterRegistry } from "../types/ethers-contracts/index.js";
import {describe, it} from "node:test";

async function getEthers() {
  const { ethers } = await network.connect();
  return ethers;
}

describe("EvidenceRegistry", function () {
  const roleHash = (ethers: any, s: string) => ethers.id(s);

  async function deployAll() {
    const ethers = await getEthers();
    const [admin, validator, submitter, other, custodianB, stranger] = await ethers.getSigners();

    // Deploy SubmitterRegistry and set roles
    const SubmitterFactory = await ethers.getContractFactory("SubmitterRegistry");
    const submitterRegistry = (await SubmitterFactory.deploy(admin.address)) as SubmitterRegistry;

    await submitterRegistry.connect(admin).grantRole(roleHash(ethers, "VALIDATOR"), validator.address);

    const EvidenceFactory = await ethers.getContractFactory("EvidenceRegistry");
    const evidence = (await EvidenceFactory.deploy(admin.address, submitterRegistry.getAddress())) as EvidenceRegistry;

    return { ethers, admin, validator, submitter, other, custodianB, stranger, submitterRegistry, evidence };
  }

  it("sets up admin role on deploy and requires non-zero registry", async function () {
    const { ethers, admin, submitterRegistry } = await deployAll();

    const EvidenceFactory = await ethers.getContractFactory("EvidenceRegistry");
    await expect(EvidenceFactory.deploy(admin.address, ethers.ZeroAddress as any)).to.be.revertedWith(
      "registry required"
    );

    const evidence = await EvidenceFactory.deploy(admin.address, submitterRegistry.getAddress());
    expect(await evidence.hasRole(await evidence.DEFAULT_ADMIN_ROLE(), admin.address)).to.equal(true);
  });

  it("allows a registered submitter to anchor an evidence", async function () {
    const { ethers, submitter, validator, submitterRegistry, evidence } = await deployAll();

    // Register submitter in the SubmitterRegistry
    const profileHash = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    const jurisdiction = "QC-CA";
    await submitterRegistry.connect(submitter).registerSubmitter(profileHash, jurisdiction);

    // Optionally verify to ensure also works for verified users
    const until = BigInt(Math.floor(Date.now() / 1000) + 365 * 24 * 3600);
    await submitterRegistry.connect(validator).verifySubmitter(submitter.address, 2, until, 1n);

    const evidenceId = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    const contentHash = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    const caseRef = "C-2025-000012";
    const kind = "PHOTO";
    const mediaURI = "ipfs://bafy...";

    const tx = evidence
      .connect(submitter)
      .anchorEvidence(evidenceId, contentHash, caseRef, jurisdiction, kind, mediaURI);

    await expect(tx)
      .to.emit(evidence, "EvidenceAnchored")
      .withArgs(
        evidenceId,
        submitter.address,
        submitter.address,
        contentHash,
        caseRef,
        jurisdiction,
        kind,
        mediaURI,
        anyValue
      );

    const state = await evidence.stateOf(evidenceId);
    expect(state.exists).to.equal(true);
    expect(state.submitter).to.equal(submitter.address);
    expect(state.contentHash).to.equal(contentHash);
    expect(state.currentCustodian).to.equal(submitter.address);
    expect(state.jurisdiction).to.equal(jurisdiction);
    expect(state.kind).to.equal(kind);
    expect(state.mediaURI).to.equal(mediaURI);

    expect(await evidence.currentCustodian(evidenceId)).to.equal(submitter.address);
  });

  it("rejects anchor from non-registered address and when paused", async function () {
    const { ethers, other, admin, submitterRegistry, evidence } = await deployAll();

    const evidenceId = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    const contentHash = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;

    await expect(
      evidence.connect(other).anchorEvidence(evidenceId, contentHash, "C-1", "QC-CA", "DOC", "uri")
    ).to.be.revertedWith("submitter not registered");

    await expect(evidence.connect(admin).pause()).to.emit(evidence, "Paused");
    await expect(
      evidence.connect(other).anchorEvidence(evidenceId, contentHash, "C-1", "QC-CA", "DOC", "uri")
    ).to.be.revertedWithCustomError(evidence, "EnforcedPause");
  });
});
