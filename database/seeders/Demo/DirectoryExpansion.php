<?php

namespace Database\Seeders\Demo;

/**
 * Fills a demo directory out to a fixed size while keeping the named people
 * the tests already know. Generated rows are fictional and cycle the
 * organisation's departments, locations, and work patterns.
 */
final class DirectoryExpansion
{
    /**
     * @param  array<string, array<string, mixed>>  $directory
     * @param  list<string>  $departments
     * @param  list<string>  $locations
     * @param  list<array<string, string>>  $profiles
     * @return array<string, array<string, mixed>>
     */
    public static function pad(array $directory, string $domain, string $numberPrefix, array $departments, array $locations, array $profiles, int $target = 100): array
    {
        $sequence = count($directory) + 1;

        while (count($directory) < $target) {
            $email = sprintf('sample.%03d@%s', $sequence, $domain);
            $profile = $profiles[($sequence - 1) % count($profiles)];
            $directory[$email] = [
                'member_id' => $numberPrefix.'-'.$sequence,
                'first_name' => 'Sample',
                'last_name' => sprintf('%03d', $sequence),
                'department' => $departments[($sequence - 1) % count($departments)],
                'location' => $locations[($sequence - 1) % count($locations)],
                'work_type' => $profile['work_type'],
                'category' => $profile['category'],
                'grade' => $profile['grade'],
                'employment_type' => $profile['employment_type'],
                'started_on' => $profile['started_on'],
                'status' => $profile['status'],
            ];
            $sequence++;
        }

        return $directory;
    }
}
