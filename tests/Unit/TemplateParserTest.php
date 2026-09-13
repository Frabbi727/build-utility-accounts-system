<?php

namespace Tests\Unit;

use App\Services\Notification\TemplateParser;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TemplateParserTest extends TestCase
{
    private TemplateParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TemplateParser;
    }

    public function test_parses_bill_template_with_string_and_numeric_data(): void
    {
        $template = 'Hello {resident_name}, your utility bill for {billing_month} of ৳{amount} is due on {due_date}. Please pay to avoid late fees.';

        $data = [
            'resident_name' => 'John Doe',
            'billing_month' => 'September 2026',
            'amount' => 1500.50,
            'due_date' => '15 Sep 2026',
        ];

        $result = $this->parser->parse($template, $data);

        $this->assertSame(
            'Hello John Doe, your utility bill for September 2026 of ৳1,500.50 is due on 15 Sep 2026. Please pay to avoid late fees.',
            $result
        );
    }

    public function test_parses_bill_template_with_carbon_dates_and_formatted_amount(): void
    {
        $template = 'Hello {resident_name}, your utility bill for {billing_month} of ৳{amount} is due on {due_date}.';

        $data = [
            'resident_name' => 'Jane Smith',
            'billing_month' => Carbon::create(2026, 9, 1),
            'amount' => '2,350.00',
            'due_date' => Carbon::create(2026, 9, 20),
        ];

        $result = $this->parser->parse($template, $data);

        $this->assertSame(
            'Hello Jane Smith, your utility bill for September 2026 of ৳2,350.00 is due on 20 Sep 2026.',
            $result
        );
    }

    public function test_parses_maintenance_template_with_ticket_data(): void
    {
        $template = 'Your ticket "{ticket_title}" status changed to {ticket_status}. {resolution_notes}';

        $data = [
            'ticket_id' => 101,
            'ticket_title' => 'Water Leakage',
            'ticket_status' => 'Resolved',
            'resolution_notes' => 'Replaced the pipe valve.',
        ];

        $result = $this->parser->parse($template, $data);

        $this->assertSame(
            'Your ticket "Water Leakage" status changed to Resolved. Replaced the pipe valve.',
            $result
        );
    }

    public function test_parses_maintenance_assignment_template(): void
    {
        $template = 'Technician {assigned_to} has been assigned to your ticket #{ticket_id} for flat {flat_number} in {building_name}.';

        $data = [
            'assigned_to' => 'Rahim Khan',
            'ticket_id' => 42,
            'flat_number' => '4B',
            'building_name' => 'Sunrise Tower',
        ];

        $result = $this->parser->parse($template, $data);

        $this->assertSame(
            'Technician Rahim Khan has been assigned to your ticket #42 for flat 4B in Sunrise Tower.',
            $result
        );
    }

    public function test_handles_missing_and_null_tokens_gracefully_with_clean_whitespace(): void
    {
        $template = 'Your ticket "{ticket_title}" status changed to {ticket_status}. {resolution_notes}';

        $data = [
            'ticket_title' => 'AC Repair',
            'ticket_status' => 'In Progress',
            'resolution_notes' => null,
        ];

        $result = $this->parser->parse($template, $data);

        $this->assertSame(
            'Your ticket "AC Repair" status changed to In Progress.',
            $result
        );
    }

    public function test_handles_unprovided_tokens_without_errors(): void
    {
        $template = 'Payment Reminder: Bill for Flat {flat_number} in {building_name}';

        $data = [
            'flat_number' => '3A',
        ];

        $result = $this->parser->parse($template, $data);

        $this->assertSame(
            'Payment Reminder: Bill for Flat 3A in',
            $result
        );
    }

    public function test_handles_empty_template_and_unclosed_braces(): void
    {
        $this->assertSame('', $this->parser->parse(''));
        $this->assertSame(
            'Hello {resident_name, bill is 500.00',
            $this->parser->parse('Hello {resident_name, bill is {amount}', ['amount' => 500])
        );
    }

    public function test_amount_formats_integer_and_float_gracefully(): void
    {
        $template = 'Total: {amount}';

        $this->assertSame('Total: 1,500.00', $this->parser->parse($template, ['amount' => 1500]));
        $this->assertSame('Total: 2,450.75', $this->parser->parse($template, ['amount' => 2450.75]));
        $this->assertSame('Total: 3,000.00', $this->parser->parse($template, ['amount' => '3000']));
        $this->assertSame('Total: 1,234.56', $this->parser->parse($template, ['amount' => '1,234.56']));
    }

    public function test_extracts_tokens_correctly(): void
    {
        $template = 'Hello {resident_name}, your bill of {amount} for flat {flat_number} is due on {due_date}. Flat: {flat_number}';

        $tokens = $this->parser->extractTokens($template);

        $this->assertSame(['resident_name', 'amount', 'flat_number', 'due_date'], $tokens);
    }

    public function test_static_render_and_tokens_helpers(): void
    {
        $rendered = TemplateParser::render('Hello {resident_name}', ['resident_name' => 'Alice']);
        $this->assertSame('Hello Alice', $rendered);

        $tokens = TemplateParser::tokens('Hello {resident_name}, ticket #{ticket_id}');
        $this->assertSame(['resident_name', 'ticket_id'], $tokens);
    }
}
