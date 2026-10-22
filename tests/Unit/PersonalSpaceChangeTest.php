<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Configuration\PersonalSpaceConfig;
use Samla\PluginApi\{MutationContext,PersonalSpaceChange,Scope};
final class PersonalSpaceChangeTest extends TestCase
{
    public function testOwnershipUsesStableAccountAndExactSelfProvisioningContext():void
    {
        $a=new PersonalSpaceChange('alice','Persönlich: Alice',1000);$b=new PersonalSpaceChange('alice','Neuer Name',2000);
        self::assertSame($a->spaceId,$b->spaceId);self::assertSame($a->groupId,$b->groupId);
        self::assertNotSame($a->spaceId,(new PersonalSpaceChange('bob',$a->name,1000))->spaceId);
        $targets=['space'=>$a->spaceId,'owner'=>'alice','group'=>$a->groupId];
        $c=new MutationContext(str_repeat('a',32),'alice','storage.space.personal.provision',Scope::Space,$a->spaceId,$targets,personalSpace:$a);self::assertSame($a,$c->personalSpace);
        foreach([
            fn()=>new MutationContext(str_repeat('a',32),'bob','storage.space.personal.provision',Scope::Space,$a->spaceId,$targets,personalSpace:$a),
            fn()=>new MutationContext(str_repeat('a',32),'alice','storage.space.personal.provision',Scope::Space,'other',$targets,personalSpace:$a),
            fn()=>new MutationContext(str_repeat('a',32),'alice','storage.space.personal.provision',Scope::Space,$a->spaceId,$targets),
        ]as$make){try{$make();self::fail('Misbound owner accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function testConfigIsBoundedAndCanDisableOnlyNewProvisioning():void
    {
        self::assertSame(10737418240,PersonalSpaceConfig::fromEnvironment([])->quotaBytes);
        self::assertFalse(PersonalSpaceConfig::fromEnvironment(['APP_PERSONAL_SPACE_ENABLED'=>'0'])->enabled);
        self::assertSame(12345,PersonalSpaceConfig::fromEnvironment(['APP_PERSONAL_SPACE_QUOTA_BYTES'=>'12345'])->quotaBytes);
        foreach([
            ['APP_PERSONAL_SPACE_ENABLED'=>'yes'],['APP_PERSONAL_SPACE_QUOTA_BYTES'=>'0'],['APP_PERSONAL_SPACE_QUOTA_BYTES'=>'1099511627777'],['APP_PERSONAL_SPACE_QUOTA_BYTES'=>[]],
        ]as$env){try{PersonalSpaceConfig::fromEnvironment($env);self::fail('Invalid personal setting accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
}
