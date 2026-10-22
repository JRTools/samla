<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface PluginRegistrar
{
    public function permission(Permission $permission): void;
    public function authorizationProvider(AuthorizationProvider $provider): void;
    public function validator(ChangeValidator $validator, int $priority = 0): void;
    public function restriction(AccessRestriction $restriction, int $priority = 0): void;
    public function outboxSubscriber(OutboxSubscription $subscription): void;
    public function exportProvider(ExportMetadataProvider $provider):void;
    public function mediaField(MediaField $field):void;
    public function mediaProvider(MediaProvider $provider,int $priority=0):void;
}
