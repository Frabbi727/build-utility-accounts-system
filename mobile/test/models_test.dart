import 'package:flutter_test/flutter_test.dart';
import 'package:resident_mobile_app/features/bills/models/bill_model.dart';
import 'package:resident_mobile_app/features/dashboard/models/dashboard_state.dart';
import 'package:resident_mobile_app/features/maintenance/models/ticket_model.dart';
import 'package:resident_mobile_app/features/notices/models/notice_model.dart';
import 'package:resident_mobile_app/features/payments/models/payment_model.dart';

void main() {
  group('BillModel and BillItemModel', () {
    test('parses json with itemized charges and dynamic ledger fields', () {
      final json = {
        'id': 101,
        'bill_no': 'BILL-2026-001',
        'billing_month': '2026-09',
        'billing_month_formatted': 'September 2026',
        'status': 'unpaid',
        'total_amount': '5500.00',
        'paid_amount': '1500.00',
        'due_amount': '4000.00',
        'month_charges': '5500.00',
        'total_due': '4000.00',
        'advance_held': '500.00',
        'arrears': '0.00',
        'due_date': '2026-09-25',
        'is_overdue': false,
        'download_pdf_url': 'https://uas.test/bills/101/print',
        'items': [
          {
            'id': 1,
            'description': 'Service Charge',
            'charge_head_name': 'Service Charge',
            'amount': '3500.00',
            'quantity': '1',
            'unit_rate': '3500.00',
            'unit_label': 'month',
          },
          {
            'id': 2,
            'description': 'Water Usage',
            'charge_head_name': 'Water Usage',
            'amount': '2000.00',
            'quantity': '20',
            'unit_rate': '100.00',
            'unit_label': 'units',
          },
        ],
      };

      final bill = BillModel.fromJson(json);

      expect(bill.id, 101);
      expect(bill.billNo, 'BILL-2026-001');
      expect(bill.status, 'unpaid');
      expect(bill.isUnpaid, true);
      expect(bill.isPaid, false);
      expect(bill.totalAmount, '5500.00');
      expect(bill.paidAmount, '1500.00');
      expect(bill.dueAmount, '4000.00');
      expect(bill.items.length, 2);
      expect(bill.items[0].chargeHeadName, 'Service Charge');
      expect(bill.items[1].quantity, '20');
    });
  });

  group('Dashboard Models', () {
    test('FlatItem parses json correctly', () {
      final json = {
        'id': 5,
        'number': '4A',
        'role': 'owner',
        'building_name': 'Sunflower Tower',
        'building_code': 'ST-01',
      };

      final flat = FlatItem.fromJson(json);
      expect(flat.id, 5);
      expect(flat.number, '4A');
      expect(flat.role, 'owner');
      expect(flat.buildingName, 'Sunflower Tower');
    });

    test('ResidentBalances parses ledger values correctly', () {
      final json = {
        'total_due': '4500.00',
        'advance_held': '1000.00',
        'current_month_charges': '5500.00',
        'arrears': '0.00',
        'currency': 'BDT',
      };

      final balances = ResidentBalances.fromJson(json);
      expect(balances.totalDue, '4500.00');
      expect(balances.totalDueDouble, 4500.00);
      expect(balances.hasOutstandingDue, true);
      expect(balances.advanceHeld, '1000.00');
    });
  });

  group('Payment Models', () {
    test('PaymentSubmissionModel parses pending submission', () {
      final json = {
        'id': 12,
        'flat_id': 5,
        'amount': '3500.00',
        'payment_method': 'bKash',
        'reference_number': '9J4K82L10Q',
        'slip_url': 'https://uas.test/slips/12.jpg',
        'status': 'pending',
        'rejection_reason': null,
        'created_at': '2026-09-12T10:00:00Z',
        'verified_at': null,
      };

      final sub = PaymentSubmissionModel.fromJson(json);
      expect(sub.id, 12);
      expect(sub.amount, '3500.00');
      expect(sub.isPending, true);
      expect(sub.isVerified, false);
      expect(sub.isRejected, false);
    });

    test('PaymentReceiptModel parses posted payment receipt', () {
      final json = {
        'id': 34,
        'payment_no': 'PAY-2026-0034',
        'flat_id': 5,
        'amount': '3500.00',
        'payment_date': '2026-09-12',
        'payment_method': 'bKash',
        'receipt_url': 'https://uas.test/receipts/34.pdf',
        'created_at': '2026-09-12T10:30:00Z',
      };

      final receipt = PaymentReceiptModel.fromJson(json);
      expect(receipt.paymentNo, 'PAY-2026-0034');
      expect(receipt.amount, '3500.00');
      expect(receipt.receiptUrl, 'https://uas.test/receipts/34.pdf');
    });
  });

  group('Maintenance & Notice Models', () {
    test('MaintenanceTicketModel parses correctly', () {
      final json = {
        'id': 7,
        'flat_id': 5,
        'category': 'Plumbing',
        'title': 'Bathroom leak',
        'description': 'Water leaking from basin pipe',
        'priority': 'urgent',
        'status': 'in_progress',
        'assigned_to_name': 'Karim (Plumber)',
        'created_at': '2026-09-11T12:00:00Z',
        'resolved_at': null,
      };

      final ticket = MaintenanceTicketModel.fromJson(json);
      expect(ticket.id, 7);
      expect(ticket.category, 'Plumbing');
      expect(ticket.isInProgress, true);
      expect(ticket.assignedToName, 'Karim (Plumber)');
    });

    test('NoticeModel parses correctly', () {
      final json = {
        'id': 3,
        'title': 'Generator Maintenance Notice',
        'content': 'Generator will be under scheduled maintenance tomorrow from 2pm to 4pm.',
        'is_pinned': true,
        'publish_date': '2026-09-12',
        'created_at': '2026-09-12T08:00:00Z',
      };

      final notice = NoticeModel.fromJson(json);
      expect(notice.id, 3);
      expect(notice.isPinned, true);
      expect(notice.title, 'Generator Maintenance Notice');
    });
  });
}
