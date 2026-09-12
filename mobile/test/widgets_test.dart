import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:resident_mobile_app/core/widgets/app_button.dart';
import 'package:resident_mobile_app/core/widgets/confirm_dialog.dart';

void main() {
  group('AppButton Widget Tests', () {
    testWidgets('renders text and responds to tap when not loading', (tester) async {
      bool tapped = false;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(
              text: 'Pay Now',
              isLoading: false,
              onPressed: () {
                tapped = true;
              },
            ),
          ),
        ),
      );

      expect(find.text('Pay Now'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsNothing);

      await tester.tap(find.byType(AppButton));
      await tester.pump();

      expect(tapped, true);
    });

    testWidgets('shows loading indicator and ignores tap when isLoading is true', (tester) async {
      bool tapped = false;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(
              text: 'Pay Now',
              isLoading: true,
              onPressed: () {
                tapped = true;
              },
            ),
          ),
        ),
      );

      expect(find.text('Pay Now'), findsNothing);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      await tester.tap(find.byType(AppButton));
      await tester.pump();

      // Double-submission blocked!
      expect(tapped, false);
    });
  });

  group('ConfirmDialog Widget Tests', () {
    testWidgets('displays title, message, details and responds to confirm/cancel', (tester) async {
      bool? result;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () async {
                  result = await ConfirmDialog.show(
                    context,
                    title: 'Approve Payment',
                    message: 'Are you sure you want to approve this?',
                    details: {
                      'Amount': '৳ 3500.00',
                      'Flat': 'Flat 4A',
                    },
                    confirmText: 'Yes, Approve',
                    cancelText: 'No, Cancel',
                  );
                },
                child: const Text('Open Dialog'),
              ),
            ),
          ),
        ),
      );

      // Open dialog
      await tester.tap(find.text('Open Dialog'));
      await tester.pumpAndSettle();

      expect(find.text('Approve Payment'), findsOneWidget);
      expect(find.text('Are you sure you want to approve this?'), findsOneWidget);
      expect(find.text('Amount'), findsOneWidget);
      expect(find.text('৳ 3500.00'), findsOneWidget);
      expect(find.text('Flat'), findsOneWidget);
      expect(find.text('Flat 4A'), findsOneWidget);

      // Tap Confirm
      await tester.tap(find.text('Yes, Approve'));
      await tester.pumpAndSettle();

      expect(result, true);
    });
  });
}
