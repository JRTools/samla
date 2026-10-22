<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{AccessProfile,AccessProfileChange,MutationContext,Scope,StorageSpaceChange};

final class AccessAdministrationContractTest extends TestCase
{
    public function testExactProfileProposalCannotChangeItsOperationScopeOrTarget():void
    {
        $profile=new AccessProfile('test.read','Lesen','Dateien lesen',Scope::Space,['files.read','files.list']);
        $proposal=new AccessProfileChange($profile,'team','space',true,str_repeat('a',64));
        $context=new MutationContext(str_repeat('a',32),'admin','rbac.profile.grant',Scope::Space,'space',['group'=>'team','role'=>'test.read'],accessProfile:$proposal);
        self::assertSame($proposal,$context->accessProfile);
        foreach([
            ['rbac.profile.revoke',Scope::Space,'space',['group'=>'team','role'=>'test.read'],$proposal],
            ['rbac.profile.grant',Scope::Global,null,['group'=>'team','role'=>'test.read'],$proposal],
            ['rbac.profile.grant',Scope::Space,'other',['group'=>'team','role'=>'test.read'],$proposal],
            ['rbac.profile.grant',Scope::Space,'space',['group'=>'other','role'=>'test.read'],$proposal],
            ['rbac.profile.grant',Scope::Space,'space',['group'=>'team','role'=>'test.read'],null],
        ]as[$operation,$scope,$space,$targets,$change]){
            try{new MutationContext(str_repeat('a',32),'admin',$operation,$scope,$space,$targets,accessProfile:$change);self::fail('Expected rejected proposal binding.');}
            catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
    public function testProfileFieldsAreExplicitBoundedAndIndependentOfInputReferences():void
    {
        $permission='files.read';$permissions=[&$permission,'files.list','files.read'];
        $profile=new AccessProfile('test.read','Lesen','Beschreibung',Scope::Space,$permissions);$permission='rbac.roles.manage';
        self::assertSame(['files.list','files.read'],$profile->permissions);
        foreach([[],['*'],[1],array_fill(0,65,'files.read')]as$invalid){
            try{new AccessProfile('test.read','Lesen','Beschreibung',Scope::Space,$invalid);self::fail('Expected explicit bounded permissions.');}
            catch(\InvalidArgumentException){self::assertTrue(true);}
        }
        try{new AccessProfileChange($profile,'team',null,true,str_repeat('a',64));self::fail('Expected matching space.');}
        catch(\InvalidArgumentException){self::assertTrue(true);}
        try{new AccessProfileChange($profile,'team','space',true,'invalid');self::fail('Expected state fingerprint.');}
        catch(\InvalidArgumentException){self::assertTrue(true);}
    }
    public function testSpaceProposalBindsNameQuotaAndDeleteIntentToTheContext():void
    {
        $change=new StorageSpaceChange('space','update',str_repeat('a',64),'Älmhult',100000000000);
        self::assertSame($change,(new MutationContext(str_repeat('a',32),'admin','storage.space.catalog.update',Scope::Space,'space',['space'=>'space'],storageSpace:$change))->storageSpace);
        foreach([
            fn()=>new StorageSpaceChange('space','create',str_repeat('a',64),'Team',100),
            fn()=>new StorageSpaceChange('space','delete',str_repeat('a',64),'Team',null),
            fn()=>new StorageSpaceChange('space','update',null,'Team',100),
            fn()=>new StorageSpaceChange('space','create',null,'Team',0),
            fn()=>new StorageSpaceChange('space','create',null,'Team',1099511627777),
            fn()=>new StorageSpaceChange('space','create',null,"Team\nName",100),
            fn()=>new MutationContext(str_repeat('a',32),'admin','storage.space.catalog.delete',Scope::Space,'space',['space'=>'space'],storageSpace:$change),
            fn()=>new MutationContext(str_repeat('a',32),'admin','storage.space.catalog.update',Scope::Space,'other',['space'=>'space'],storageSpace:$change),
        ]as$make){try{$make();self::fail('Expected rejected space proposal.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
}
