<?php

namespace Armancodes\DownloadLink;

use Armancodes\DownloadLink\Models\DownloadLink;
use Armancodes\DownloadLink\Models\DownloadLinkIpAddress;
use Armancodes\DownloadLink\Models\DownloadLinkUser;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadLinkGenerator
{
    private $filePath;
    private $fileName;
    private $disk;
    private $authOnly;
    private $guestOnly;
    private $expireTime;
    private $limitIp;
    private $allowIp;
    private $userId;

    const DOWNLOAD_LINK_NUMBER_OF_CHARACTERS = 64;

    public function __construct()
    {
        $this->authOnly = false;
        $this->guestOnly = false;
    }

    public function filePath(string $filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function fileName(string $fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function disk(string $disk)
    {
        $this->disk = $disk;

        return $this;
    }

    public function auth()
    {
        $this->authOnly = true;
        $this->guestOnly = false;

        return $this;
    }

    public function guest()
    {
        $this->guestOnly = true;
        $this->authOnly = false;

        return $this;
    }

    public function expire($expireTime)
    {
        $this->expireTime = $expireTime;

        return $this;
    }

    public function limitIp($limitIp)
    {
        $this->limitIp = $limitIp;

        return $this;
    }

    public function allowIp($allowIp)
    {
        $this->allowIp = $allowIp;

        return $this;
    }

    public function for($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    public function generate()
    {
        $this->isRequiredDataPassed();

        $this->fileExists();

        DB::beginTransaction();

        $downloadLink = $this->createDownloadLink();

        $this->attachIpToLink($downloadLink->id);

        $this->attachUserToLink($downloadLink->id);

        DB::commit();

        return $downloadLink->link;
    }

    public function delete(string $link): void
    {
        $link = $this->getDownloadLink($link);

        $link->delete();
    }

    private function attachIpToLink(int $downloadLinkId): void
    {
        $allowedIps = collect($this->allowIp);
        $limitedIps = collect($this->limitIp);

        $allowedIps->each(function ($ip, $key) use ($downloadLinkId) {
            $this->createIpAddressForDownloadLink($ip, $downloadLinkId, true);
        });

        if (! $allowedIps->all()) {
            $limitedIps->each(function ($ip, $key) use ($downloadLinkId) {
                $this->createIpAddressForDownloadLink($ip, $downloadLinkId);
            });
        }
    }

    private function attachUserToLink(int $downloadLinkId): void
    {
        $users = collect($this->userId);

        $users->each(function ($userId, $key) use ($downloadLinkId) {
            $this->createUserForDownloadLink($userId, $downloadLinkId);
        });
    }

    private function createIpAddressForDownloadLink($ip, int $downloadLinkId, $allowed = false)
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            DB::rollBack();

            throw new Exception("Given IP is NOT valid!");
        }

        DownloadLinkIpAddress::create([
            'download_link_id' => $downloadLinkId,
            'ip_address' => $ip,
            'allowed' => $allowed,
        ]);
    }

    private function createUserForDownloadLink(int $userId, int $downloadLinkId)
    {
        DownloadLinkUser::create([
            'download_link_id' => $downloadLinkId,
            'user_id' => $userId,
        ]);
    }

    private function fileExists(): void
    {
        if (! Storage::disk($this->disk)->exists($this->filePath)) {
            throw new Exception("File not found!");
        }
    }

    private function getDownloadLink(string $link)
    {
        $downloadLink = DownloadLink::where('link', $link)->first();

        if (! $downloadLink) {
            throw new Exception("Link NOT found!");
        }

        return $downloadLink;
    }

    private function createDownloadLink()
    {
        $expireTime = Carbon::make($this->expireTime);

        return DownloadLink::create([
            'link' => Str::random(self::DOWNLOAD_LINK_NUMBER_OF_CHARACTERS),
            'disk' => $this->disk,
            'file_path' => $this->filePath,
            'file_name' => $this->fileName,
            'auth_only' => $this->authOnly,
            'guest_only' => $this->guestOnly,
            'expire_time' => $expireTime,
        ]);
    }

    private function isRequiredDataPassed()
    {
        if (! $this->filePath) {
            throw new Exception("File path must NOT be empty!");
        }

        if (! $this->disk) {
            throw new Exception("Disk must NOT be empty!");
        }

        if (! config('filesystems.disks.' . $this->disk)) {
            throw new Exception("Disk is NOT valid!");
        }

        if (! $this->fileName) {
            $this->fileName = array_reverse(explode('/', $this->filePath))[0];
        }
    }
}
