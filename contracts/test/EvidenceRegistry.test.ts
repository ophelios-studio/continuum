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

  it("accept custody transfer", async function () {
    const { ethers, submitter, validator, custodianB, submitterRegistry, evidence } = await deployAll();

    // Register and anchor
    const ph = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    await submitterRegistry.connect(submitter).registerSubmitter(ph, "US-NY");
    const eid = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    const ch = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    await evidence.connect(submitter).anchorEvidence(eid, ch, "C-2", "US-NY", "FILE", "uri");

    const purpose = "Forensics Lab";
    const expectedReturnAt = BigInt(Math.floor(Date.now() / 1000) + 7 * 24 * 3600);
    const ctx = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;

    await expect(
      evidence.connect(submitter).initiateTransfer(eid, custodianB.address, purpose, Number(expectedReturnAt), ctx)
    )
      .to.emit(evidence, "CustodyTransferInitiated")
      .withArgs(eid, submitter.address, custodianB.address, purpose, Number(expectedReturnAt), ctx);

    await expect(evidence.connect(custodianB).acceptCustody(eid))
      .to.emit(evidence, "CustodyAccepted")
      .withArgs(eid, submitter.address, custodianB.address, anyValue);

    expect(await evidence.currentCustodian(eid)).to.equal(custodianB.address);
  });

  it("returns custody (auto-accept)", async function () {
    const { ethers, submitter, custodianB, validator, submitterRegistry, evidence } = await deployAll();

    // Register and anchor
    const ph = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    await submitterRegistry.connect(submitter).registerSubmitter(ph, "US-MA");
    const eid = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    const ch = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    await evidence.connect(submitter).anchorEvidence(eid, ch, "C-3", "US-MA", "IMG", "uri");

    const note = "Returned to locker";
    await expect(evidence.connect(submitter).returnCustody(eid, custodianB.address, note))
      .to.emit(evidence, "CustodyReturned")
      .withArgs(eid, submitter.address, custodianB.address, note, anyValue);

    expect(await evidence.currentCustodian(eid)).to.equal(custodianB.address);
  });

  it("reverts on invalid actions and bad recipients", async function () {
    const { ethers, submitter, other, custodianB, submitterRegistry, evidence } = await deployAll();

    const ph = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    await submitterRegistry.connect(submitter).registerSubmitter(ph, "US-WA");
    const eid = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    const ch = ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`;
    await evidence.connect(submitter).anchorEvidence(eid, ch, "C-4", "US-WA", "DOC", "uri");

    // Only current custodian can initiate
    await expect(
      evidence.connect(other).initiateTransfer(eid, custodianB.address, "p", 0, ethers.ZeroHash as `0x${string}`)
    ).to.be.revertedWith("only custodian");

    // bad recipients
    await expect(
      evidence.connect(submitter).initiateTransfer(eid, ethers.ZeroAddress, "p", 0, ethers.ZeroHash as `0x${string}`)
    ).to.be.revertedWith("bad recipient");
    await expect(
      evidence.connect(submitter).initiateTransfer(eid, submitter.address, "p", 0, ethers.ZeroHash as `0x${string}`)
    ).to.be.revertedWith("bad recipient");

    // No pending transfer
    await expect(evidence.connect(custodianB).acceptCustody(eid)).to.be.revertedWith("no pending transfer");

    // Initiate then only pending custodian may accept
    await evidence
      .connect(submitter)
      .initiateTransfer(eid, custodianB.address, "p", 0, ethers.ZeroHash as `0x${string}`);

    await expect(evidence.connect(other).acceptCustody(eid)).to.be.revertedWith("only pending custodian");

    // returnCustody: only current custodian and bad recipients reverts
    await expect(evidence.connect(other).returnCustody(eid, submitter.address, "n")).to.be.revertedWith(
      "only custodian"
    );
    await expect(
      evidence.connect(submitter).returnCustody(eid, submitter.address, "n")
    ).to.be.revertedWith("bad recipient");
    await expect(
      evidence.connect(submitter).returnCustody(eid, ethers.ZeroAddress, "n")
    ).to.be.revertedWith("bad recipient");
  });
});
