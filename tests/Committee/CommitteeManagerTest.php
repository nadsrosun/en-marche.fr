<?php

namespace Tests\AppBundle\Committee;

use AppBundle\Collection\AdherentCollection;
use AppBundle\Committee\CommitteeManager;
use AppBundle\DataFixtures\ORM\LoadAdherentData;
use AppBundle\DataFixtures\ORM\LoadCommitteeMembershipHistoryData;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\Committee;
use AppBundle\Entity\CommitteeMembership;
use AppBundle\Entity\ReferentTag;
use AppBundle\Entity\Reporting\CommitteeMembershipHistory;
use AppBundle\Exception\CommitteeMembershipException;
use AppBundle\Geocoder\Coordinates;
use Tests\AppBundle\MysqlWebTestCase;
use Tests\AppBundle\TestHelperTrait;

/**
 * @group functional
 * @group committeeManager
 */
class CommitteeManagerTest extends MysqlWebTestCase
{
    use TestHelperTrait;

    /* @var CommitteeManager */
    private $committeeManager;

    public function testGetCommitteeHosts()
    {
        $this->assertInstanceOf(
            AdherentCollection::class,
            $hosts = $this->committeeManager->getCommitteeHosts($this->getCommittee(LoadAdherentData::COMMITTEE_1_UUID))
        );
        // Approved committees
        $this->assertCount(2, $hosts);
        $this->assertCount(2, $this->committeeManager->getCommitteeHosts($this->getCommittee(LoadAdherentData::COMMITTEE_3_UUID)));
        $this->assertCount(1, $this->committeeManager->getCommitteeHosts($this->getCommittee(LoadAdherentData::COMMITTEE_4_UUID)));
        $this->assertCount(1, $this->committeeManager->getCommitteeHosts($this->getCommittee(LoadAdherentData::COMMITTEE_5_UUID)));

        // Unapproved committees
        $this->assertCount(0, $this->committeeManager->getCommitteeHosts($this->getCommittee(LoadAdherentData::COMMITTEE_2_UUID)));
    }

    public function testGetCommitteeFollowers()
    {
        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_1_UUID);
        $this->assertInstanceOf(AdherentCollection::class, $hosts = $this->committeeManager->getCommitteeFollowers($committee));

        // Approved committees
        $this->assertCount(4, $hosts);
        $this->assertCount(2, $this->committeeManager->getCommitteeFollowers($committee, CommitteeManager::EXCLUDE_HOSTS));

        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_3_UUID);
        $this->assertCount(2, $this->committeeManager->getCommitteeFollowers($committee));
        $this->assertCount(0, $this->committeeManager->getCommitteeFollowers($committee, CommitteeManager::EXCLUDE_HOSTS));

        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_4_UUID);
        $this->assertCount(2, $this->committeeManager->getCommitteeFollowers($committee));
        $this->assertCount(1, $this->committeeManager->getCommitteeFollowers($committee, CommitteeManager::EXCLUDE_HOSTS));

        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_5_UUID);
        $this->assertCount(3, $this->committeeManager->getCommitteeFollowers($committee));
        $this->assertCount(2, $this->committeeManager->getCommitteeFollowers($committee, CommitteeManager::EXCLUDE_HOSTS));

        // Unapproved committees
        $this->assertCount(2, $this->committeeManager->getCommitteeFollowers($this->getCommittee(LoadAdherentData::COMMITTEE_2_UUID)));
    }

    public function testGetOptinCommitteeFollowers()
    {
        // Approved committees
        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_1_UUID);

        $this->assertInstanceOf(AdherentCollection::class, $followers = $this->committeeManager->getOptinCommitteeFollowers($committee));
        $this->assertCount(3, $followers, 'One follower has disabled the committees notifications');
        $this->assertCount(2, $this->committeeManager->getOptinCommitteeFollowers($this->getCommittee(LoadAdherentData::COMMITTEE_3_UUID)));
        $this->assertCount(2, $this->committeeManager->getOptinCommitteeFollowers($this->getCommittee(LoadAdherentData::COMMITTEE_4_UUID)));
        $this->assertCount(3, $this->committeeManager->getOptinCommitteeFollowers($this->getCommittee(LoadAdherentData::COMMITTEE_5_UUID)));

        // Unapproved committees
        $this->assertCount(2, $this->committeeManager->getOptinCommitteeFollowers($this->getCommittee(LoadAdherentData::COMMITTEE_2_UUID)));
    }

    public function testGetNearbyCommittees()
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_1_UUID);
        $coordinates = new Coordinates($adherent->getLatitude(), $adherent->getLongitude());

        $this->assertSame([
            LoadAdherentData::COMMITTEE_10_UUID,
            LoadAdherentData::COMMITTEE_4_UUID,
            LoadAdherentData::COMMITTEE_3_UUID,
        ], array_keys($this->committeeManager->getNearbyCommittees($coordinates)));

        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_3_UUID);
        $coordinates = new Coordinates($adherent->getLatitude(), $adherent->getLongitude());

        $this->assertSame([
            LoadAdherentData::COMMITTEE_1_UUID,
            LoadAdherentData::COMMITTEE_5_UUID,
            LoadAdherentData::COMMITTEE_3_UUID,
        ], array_keys($this->committeeManager->getNearbyCommittees($coordinates)));
    }

    public function testFollowCommittees(): void
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_1_UUID);

        $this->assertCount(0, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(0, $this->findCommitteeMembershipHistoryByAdherent($adherent));

        $committees = [
            LoadAdherentData::COMMITTEE_1_UUID,
            LoadAdherentData::COMMITTEE_3_UUID,
            LoadAdherentData::COMMITTEE_4_UUID,
        ];

        $this->committeeManager->followCommittees($adherent, $committees);

        $this->assertCount(3, $memberships = $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(3, $membershipEvents = $this->findCommitteeMembershipHistoryByAdherent($adherent));

        foreach ($memberships as $i => $membership) {
            /* @var CommitteeMembership $membership */
            $this->assertSame($committees[$i], $membership->getCommitteeUuid()->toString());
        }

        foreach ($membershipEvents as $membershipEvent) {
            /* @var CommitteeMembershipHistory $membershipEvent */
            $this->assertContains($membershipEvent->getCommittee()->getUuid()->toString(), $committees);
            $this->assertSame(LoadAdherentData::ADHERENT_1_UUID, $membershipEvent->getAdherentUuid()->toString());
            $this->assertSame('FOLLOWER', $membershipEvent->getPrivilege());
            $this->assertSame('join', $membershipEvent->getAction());
        }
    }

    public function testMembershipEventIsRecordedWhenFollowOrUnfollowCommittee(): void
    {
        $adherent = $this->getAdherentRepository()->findByUuid(LoadAdherentData::ADHERENT_1_UUID);
        $committee = $this->getCommitteeRepository()->findOneByUuid(LoadAdherentData::COMMITTEE_1_UUID);

        $this->committeeManager->followCommittee($adherent, $committee);

        $this->assertCount(1, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $membershipHistory = $this->getCommitteeMembershipHistoryRepository()->findOneBy(['adherentUuid' => $adherent->getUuid()]);

        /* @var CommitteeMembershipHistory $membershipHistory */
        $this->assertSame($committee, $membershipHistory->getCommittee());
        $this->assertSame(LoadAdherentData::ADHERENT_1_UUID, $membershipHistory->getAdherentUuid()->toString());
        $this->assertSame('FOLLOWER', $membershipHistory->getPrivilege());
        $this->assertSame('join', $membershipHistory->getAction());
        $this->assertEquals(['75008', '75'], $this->getReferentTagCodes($membershipHistory));

        $this->committeeManager->unfollowCommittee($adherent, $committee);

        $this->assertCount(0, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $membershipHistory = $this->getCommitteeMembershipHistoryRepository()->findOneBy(['adherentUuid' => $adherent->getUuid(), 'action' => 'leave']);

        /* @var CommitteeMembershipHistory $membershipHistory */
        $this->assertSame($committee, $membershipHistory->getCommittee());
        $this->assertSame(LoadAdherentData::ADHERENT_1_UUID, $membershipHistory->getAdherentUuid()->toString());
        $this->assertSame('FOLLOWER', $membershipHistory->getPrivilege());
        $this->assertSame('leave', $membershipHistory->getAction());
        $this->assertEquals(['75008', '75'], $this->getReferentTagCodes($membershipHistory));
    }

    private function getReferentTagCodes(CommitteeMembershipHistory $history): array
    {
        return array_map(
            function (ReferentTag $tag) { return $tag->getCode(); },
            $history->getReferentTags()->toArray()
        );
    }

    public function testFollowCommitteesTwice(): void
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_2_UUID);

        $this->assertCount(1, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(1, $this->findCommitteeMembershipHistoryByAdherent($adherent));

        $this->committeeManager->followCommittees($adherent, [LoadAdherentData::COMMITTEE_1_UUID]);

        $this->assertCount(1, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(1, $this->findCommitteeMembershipHistoryByAdherent($adherent));
    }

    public function testFollowThenUnfollowCommittees(): void
    {
        $adherent = $this->getAdherentRepository()->findByUuid(LoadAdherentData::ADHERENT_1_UUID);
        $committee = $this->getCommitteeRepository()->findOneByUuid(LoadAdherentData::COMMITTEE_1_UUID);

        $this->assertCount(0, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(0, $this->findCommitteeMembershipHistoryByAdherent($adherent));

        $this->committeeManager->followCommittee($adherent, $committee);

        $this->assertCount(1, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(1, $this->findCommitteeMembershipHistoryByAdherent($adherent));

        $this->committeeManager->unfollowCommittee($adherent, $committee);

        $this->assertCount(0, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(2, $this->findCommitteeMembershipHistoryByAdherent($adherent));

        $this->committeeManager->followCommittee($adherent, $committee);

        $this->assertCount(1, $this->getCommitteeMembershipRepository()->findMemberships($adherent));
        $this->assertCount(3, $this->findCommitteeMembershipHistoryByAdherent($adherent));
    }

    public function testGetAdherentCommittees()
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_3_UUID);

        // Without any fixed limit.
        $this->assertCount(8, $committees = $this->committeeManager->getAdherentCommittees($adherent));
        $this->assertSame('En Marche Paris 8', (string) $committees[0], 'Supervised committee must come first');
        $this->assertSame('En Marche Dammarie-les-Lys', (string) $committees[1], 'Hosted committee must come after supervised committees');
        $this->assertSame('En Marche - Comité de Évry', (string) $committees[2], 'Followed committee - most popular one first');
        $this->assertSame('En Marche - Comité de New York City', (string) $committees[3]);
        $this->assertSame('Antenne En Marche de Fontainebleau', (string) $committees[4]);
        $this->assertSame('En Marche - Comité de Rouen', (string) $committees[5]);
        $this->assertSame('En Marche - Comité de Berlin', (string) $committees[6]);
        $this->assertSame('En Marche - Comité de Singapour', (string) $committees[7], 'Followed committee - least popular one last');
    }

    public function testGetAdherentCommitteesSupervisor()
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_3_UUID);

        // Without any fixed limit.
        $this->assertCount(1, $committees = $this->committeeManager->getAdherentCommitteesSupervisor($adherent));
        $this->assertSame('En Marche Paris 8', (string) $committees[0]);
    }

    public function testChangePrivilegeNotDefinedPrivilege()
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_3_UUID);
        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_1_UUID);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid privilege WRONG_PRIVILEGE');

        $this->committeeManager->changePrivilege($adherent, $committee, 'WRONG_PRIVILEGE');
    }

    public function testChangePrivilegeException()
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_3_UUID);
        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_1_UUID);

        $this->expectException(CommitteeMembershipException::class);
        $this->expectExceptionMessage(sprintf('Committee membership "%s" cannot be promoted to the supervisor privilege.', $adherent->getMembershipFor($committee)->getUuid()));

        $this->committeeManager->changePrivilege($adherent, $committee, CommitteeMembership::COMMITTEE_SUPERVISOR);
    }

    public function testChangePrivilege()
    {
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_3_UUID);
        $adherent2 = $this->getAdherent(LoadAdherentData::ADHERENT_2_UUID);
        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_1_UUID);

        // Change privileges of the first member SUPERVISOR => FOLLOWER => HOST
        $this->assertEquals(true, $adherent->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isHostMember());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isFollower());

        $this->committeeManager->changePrivilege($adherent, $committee, CommitteeMembership::COMMITTEE_FOLLOWER);

        $this->assertEquals(true, $adherent->getMembershipFor($committee)->isFollower());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isHostMember());

        $this->committeeManager->changePrivilege($adherent, $committee, CommitteeMembership::COMMITTEE_HOST);

        $this->assertEquals(true, $adherent->getMembershipFor($committee)->isHostMember());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isFollower());

        // Change privileges of the second member: FOLLOWER => SUPERVISOR
        $this->assertEquals(true, $adherent2->getMembershipFor($committee)->isFollower());
        $this->assertEquals(false, $adherent2->getMembershipFor($committee)->isSupervisor());

        $this->committeeManager->changePrivilege($adherent2, $committee, CommitteeMembership::COMMITTEE_SUPERVISOR);

        $this->assertEquals(true, $adherent2->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent2->getMembershipFor($committee)->isFollower());
    }

    public function testApproveRefuseCommittee()
    {
        // Creator of committee
        $adherent = $this->getAdherent(LoadAdherentData::ADHERENT_6_UUID);
        $committee = $this->getCommittee(LoadAdherentData::COMMITTEE_2_UUID);

        $this->assertEquals(true, $adherent->getMembershipFor($committee)->isFollower());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isHostMember());

        // Approve committee
        $this->committeeManager->approveCommittee($committee);

        $this->assertEquals(true, $adherent->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isFollower());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isHostMember());

        // Refuse approved committee
        $this->committeeManager->refuseCommittee($committee);

        $this->assertEquals(true, $adherent->getMembershipFor($committee)->isFollower());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isHostMember());

        // Reapprove committee
        $this->committeeManager->approveCommittee($committee);

        $this->assertEquals(true, $adherent->getMembershipFor($committee)->isSupervisor());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isFollower());
        $this->assertEquals(false, $adherent->getMembershipFor($committee)->isHostMember());
    }

    protected function setUp()
    {
        parent::setUp();

        $this->loadFixtures([
            LoadAdherentData::class,
            LoadCommitteeMembershipHistoryData::class,
        ]);

        $this->container = $this->getContainer();
        $this->committeeManager = new CommitteeManager(
            $this->getManagerRegistry(),
            $this->get('event_dispatcher')
        );
    }

    protected function tearDown()
    {
        $this->loadFixtures([]);

        $this->container = null;
        $this->committeeManager = null;

        parent::tearDown();
    }

    private function findCommitteeMembershipHistoryByAdherent(Adherent $adherent): array
    {
        $membershipEvent = $this->getCommitteeMembershipHistoryRepository()->findBy(
            ['adherentUuid' => $adherent->getUuid()]
        );

        return $membershipEvent;
    }
}
