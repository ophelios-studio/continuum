import { expect } from "chai";
import { network } from "hardhat";
import type { SubmitterRegistry } from "../types/ethers-contracts/index.js";
import {describe, it} from "node:test";

async function getEthers() {
  const { ethers } = await network.connect();
  return ethers;
}

describe("SubmitterRegistry", function () {
  const roleHash = (ethers: any, s: string) => ethers.id(s);

  async function deploy() {
    const ethers = await getEthers();
    const [admin, validator, user1, user2, stranger] = await ethers.getSigners();

    const Factory = await ethers.getContractFactory("SubmitterRegistry");
    const registry = (await Factory.deploy(admin.address)) as SubmitterRegistry;

    await registry.connect(admin).grantRole(roleHash(ethers, "VALIDATOR"), validator.address);

    return { ethers, registry, admin, validator, user1, user2, stranger };
  }

  it("sets up roles", async function () {
    const { registry, admin } = await deploy();

    expect(await registry.hasRole(await registry.DEFAULT_ADMIN_ROLE(), admin.address)).to.equal(true);
    const ethers = await getEthers();
    expect(await registry.hasRole(roleHash(ethers, "ADMIN"), admin.address)).to.equal(true);
    expect(await registry.hasRole(roleHash(ethers, "VALIDATOR"), admin.address)).to.equal(true);
  });

  it("allows users to anchor profile", async function () {
    const { ethers, registry, user1 } = await deploy();

    const profileHash = ethers.hexlify(ethers.randomBytes(32));
    const jurisdiction = "QC-CA";

    await expect(registry.connect(user1).registerSubmitter(profileHash as `0x${string}`, jurisdiction))
      .to.emit(registry, "SubmitterRegistered")
      .withArgs(user1.address, profileHash, jurisdiction);

    const level = await registry.roleLevel(user1.address);
    // RoleLevel.DECLARED = 1
    expect(level).to.equal(1n);

    const s = await registry.submitters(user1.address);
    expect(s.profileHash).to.equal(profileHash);
    expect(s.jurisdiction).to.equal(jurisdiction);
    expect(s.verifiedUntil).to.equal(0n);
    expect(s.orgId).to.equal(0n);
  });

  it("prevents double registration", async function () {
    const { ethers, registry, user1 } = await deploy();
    const profileHash = ethers.hexlify(ethers.randomBytes(32));

    await registry.connect(user1).registerSubmitter(profileHash as `0x${string}`, "QC-CA");
    await expect(
      registry.connect(user1).registerSubmitter(profileHash as `0x${string}`, "QC-CA")
    ).to.be.revertedWith("Already registered");
  });

  it("validator can verify to L1 or L2 only", async function () {
    const { ethers, registry, validator, user1 } = await deploy();

    const profileHash = ethers.hexlify(ethers.randomBytes(32));
    await registry.connect(user1).registerSubmitter(profileHash as `0x${string}`, "NY-US");

    const until = BigInt(Math.floor(Date.now() / 1000) + 365 * 24 * 3600);
    const orgId = 42n;

    await expect(
      registry.connect(validator).verifySubmitter(user1.address, 2, until, orgId) // VERIFIED_L1 = 2
    )
      .to.emit(registry, "SubmitterVerified")
      .withArgs(user1.address, 2, until, orgId);

    expect((await registry.submitters(user1.address)).level).to.equal(2n);

    await expect(
      registry.connect(validator).verifySubmitter(user1.address, 3, until + 1000n, orgId)
    )
      .to.emit(registry, "SubmitterVerified")
      .withArgs(user1.address, 3, until + 1000n, orgId);

    expect((await registry.submitters(user1.address)).level).to.equal(3n);
  });

  it("rejects invalid verify level", async function () {
    const { ethers, registry, validator, user2 } = await deploy();

    await expect(
      registry.connect(validator).verifySubmitter(user2.address, 4, 0, 0) // REVOKED or invalid index
    ).to.be.revertedWith("Invalid level");

    // Not registered address
    await expect(
      registry.connect(validator).verifySubmitter(ethers.ZeroAddress, 2, 0, 0)
    ).to.be.revertedWith("Not registered");
  });

  it("only validator can verify and revoke", async function () {
    const { ethers, registry, user1, user2 } = await deploy();

    await registry.connect(user1).registerSubmitter(ethers.hexlify(ethers.randomBytes(32)) as `0x${string}`, "US-TX");

    await expect(
      registry.connect(user2).verifySubmitter(user1.address, 2, 0, 0)
    ).to.be.revertedWithCustomError(registry, "AccessControlUnauthorizedAccount");

    await expect(
      registry.connect(user2).revokeSubmitter(user1.address)
    ).to.be.revertedWithCustomError(registry, "AccessControlUnauthorizedAccount");
  });

  it("revoke and allows re-registering", async function () {
    const { ethers, registry, validator, user1 } = await deploy();
    const ph = ethers.hexlify(ethers.randomBytes(32));

    await registry.connect(user1).registerSubmitter(ph as `0x${string}`, "US-WA");
    await expect(registry.connect(validator).revokeSubmitter(user1.address))
      .to.emit(registry, "SubmitterRevoked")
      .withArgs(user1.address);

    const lvl = await registry.roleLevel(user1.address);
    expect(lvl).to.equal(4n); // REVOKED = 4

    // Register should be allowed
    const ph2 = ethers.hexlify(ethers.randomBytes(32));
    await expect(registry.connect(user1).registerSubmitter(ph2 as `0x${string}`, "US-WA"))
      .to.emit(registry, "SubmitterRegistered");

    expect((await registry.submitters(user1.address)).level).to.equal(1n);
  });
});
