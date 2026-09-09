<?php

namespace Tests\Unit\Support;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Support\DuesReminder;
use Tests\TestCase;

class DuesReminderTest extends TestCase
{
    public function test_it_normalizes_bangladesh_phone_numbers(): void
    {
        $this->assertSame('8801711234567', DuesReminder::sanitizePhone('01711-234567'));
        $this->assertSame('8801819000000', DuesReminder::sanitizePhone('+880 1819-000000'));
        $this->assertSame('8801711123456', DuesReminder::sanitizePhone('01711123456'));
        $this->assertSame('8801711123456', DuesReminder::sanitizePhone('8801711123456'));
        $this->assertSame('15551234567', DuesReminder::sanitizePhone('+1 (555) 123-4567'));
    }

    public function test_it_handles_null_and_empty_phone_numbers(): void
    {
        $this->assertSame('', DuesReminder::sanitizePhone(null));
        $this->assertSame('', DuesReminder::sanitizePhone(''));
        $this->assertSame('', DuesReminder::sanitizePhone('   '));

        $flat = new Flat(['number' => '101']);
        $flat->id = 1;

        $reminder = DuesReminder::for($flat, '1500.00');

        $this->assertSame('', $reminder['clean_phone']);
        $this->assertSame('', $reminder['whatsapp_url']);
        $this->assertNotEmpty($reminder['sms_text']);
    }

    public function test_it_generates_english_message_text(): void
    {
        $building = new Building(['name' => 'Sunset Towers', 'name_bn' => 'সানসেট টাওয়ার্স']);
        $owner = new Owner(['name' => 'Rahim Chowdhury', 'phone' => '01711-234567']);
        $flat = new Flat(['number' => '4B']);
        $flat->id = 10;
        $flat->setRelation('building', $building);
        $flat->setRelation('owner', $owner);

        $reminder = DuesReminder::for($flat, '3,500.00', 'en');

        $url = route('flats.statement', $flat);
        $expected = "Dear Rahim Chowdhury, Assalamu Alaikum. This is a gentle reminder that your service charge for Sunset Towers, Flat 4B has an outstanding balance of BDT 3,500.00. Please clear the dues at your earliest convenience via bKash/Nagad/Bank Transfer. You can view your bill here: {$url}. Thank you, Management Committee.";

        $this->assertSame($expected, $reminder['sms_text']);
        $this->assertSame('8801711234567', $reminder['clean_phone']);
    }

    public function test_it_generates_bengali_message_text(): void
    {
        $building = new Building(['name' => 'Sunset Towers', 'name_bn' => 'সানসেট টাওয়ার্স']);
        $owner = new Owner(['name' => 'রহিম চৌধুরী', 'phone' => '01819-000000']);
        $flat = new Flat(['number' => '4B']);
        $flat->id = 10;
        $flat->setRelation('building', $building);
        $flat->setRelation('owner', $owner);

        $reminder = DuesReminder::for($flat, '৩,৫০০', 'bn');

        $url = route('flats.statement', $flat);
        $expected = "আসসালামু আলাইকুম রহিম চৌধুরী। সানসেট টাওয়ার্স-এর ফ্ল্যাট 4B-এর সার্ভিস চার্জ বাবদ বকেয়া ৩,৫০০ টাকা পরিশোধের জন্য বিনীত অনুরোধ জানানো হচ্ছে। বিল ও স্টেটমেন্ট দেখতে ভিজিট করুন: {$url}। ধন্যবাদ, পরিচালনা কমিটি।";

        $this->assertSame($expected, $reminder['sms_text']);
        $this->assertSame('8801819000000', $reminder['clean_phone']);
    }

    public function test_it_generates_valid_whatsapp_url(): void
    {
        $building = new Building(['name' => 'Sunset Towers']);
        $owner = new Owner(['name' => 'Rahim Chowdhury', 'phone' => '01711-234567']);
        $flat = new Flat(['number' => '4B']);
        $flat->id = 10;
        $flat->setRelation('building', $building);
        $flat->setRelation('owner', $owner);

        $reminder = DuesReminder::for($flat, '3,500.00', 'en');

        $this->assertSame(
            'https://wa.me/8801711234567?text='.rawurlencode($reminder['sms_text']),
            $reminder['whatsapp_url'],
        );
    }
}
