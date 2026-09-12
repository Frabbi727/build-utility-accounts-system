import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../payments/screens/submit_payment_screen.dart';
import '../bloc/bills_bloc.dart';
import '../bloc/bills_event.dart';
import '../bloc/bills_state.dart';
import '../models/bill_model.dart';

class BillDetailScreen extends StatelessWidget {
  final int billId;

  const BillDetailScreen({super.key, required this.billId});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => BillsBloc(
        apiClient: context.read<ApiClient>(),
        storageService: context.read<SecureStorageService>(),
      )..add(BillDetailRequested(billId)),
      child: const _BillDetailView(),
    );
  }
}

class _BillDetailView extends StatelessWidget {
  const _BillDetailView();

  Future<void> _downloadPdf(BuildContext context, String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not open PDF download link.'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Bill Details'),
      ),
      body: SafeArea(
        bottom: true,
        child: BlocBuilder<BillsBloc, BillsState>(
          builder: (context, state) {
            if (state is BillsLoading) {
              return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
            }

            if (state is BillsError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline_rounded, size: 48, color: AppTheme.error),
                      const SizedBox(height: 12),
                      Text(state.message, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      AppButton(
                        text: 'Retry',
                        width: 120,
                        onPressed: () {
                          Navigator.of(context).pop();
                        },
                      ),
                    ],
                  ),
                ),
              );
            }

            if (state is BillDetailLoaded) {
              final bill = state.bill;
              return Column(
                children: [
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        // Bill Header Card
                        _buildHeaderCard(bill),
                        const SizedBox(height: 16),

                        // Itemized Breakdown Table
                        _buildItemsCard(bill),
                        const SizedBox(height: 16),

                        // Ledger Financial Summary
                        _buildSummaryCard(bill),
                        const SizedBox(height: 24),
                      ],
                    ),
                  ),

                  // Bottom Action Inset
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    decoration: BoxDecoration(
                      color: AppTheme.surface,
                      border: const Border(top: BorderSide(color: AppTheme.border)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.04),
                          blurRadius: 8,
                          offset: const Offset(0, -2),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        if (bill.downloadPdfUrl != null) ...[
                          Expanded(
                            child: OutlinedButton.icon(
                              style: OutlinedButton.styleFrom(
                                foregroundColor: AppTheme.primary,
                                side: const BorderSide(color: AppTheme.primary),
                                minimumSize: const Size.fromHeight(48),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                              ),
                              icon: const Icon(Icons.picture_as_pdf_rounded, size: 20),
                              label: const Text('PDF Bill'),
                              onPressed: () => _downloadPdf(context, bill.downloadPdfUrl!),
                            ),
                          ),
                          const SizedBox(width: 12),
                        ],
                        if (!bill.isPaid) ...[
                          Expanded(
                            child: AppButton(
                              text: 'Pay ৳ ${bill.dueAmount}',
                              icon: Icons.payment_rounded,
                              onPressed: () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (context) => SubmitPaymentScreen(
                                      prefilledAmount: bill.dueAmount,
                                      prefilledBillNo: bill.billNo,
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              );
            }

            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  Widget _buildHeaderCard(BillModel bill) {
    final isPaid = bill.isPaid;
    final isOverdue = bill.isOverdue;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                bill.billingMonthFormatted,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.textPrimary,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: isPaid
                      ? AppTheme.successLight
                      : (isOverdue ? AppTheme.errorLight : AppTheme.warningLight),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  bill.status.toUpperCase(),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: isPaid
                        ? AppTheme.success
                        : (isOverdue ? AppTheme.error : AppTheme.warning),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Bill No: ${bill.billNo}',
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
          ),
          if (bill.dueDate != null) ...[
            const SizedBox(height: 4),
            Text(
              'Due Date: ${bill.dueDate}',
              style: TextStyle(
                color: isOverdue ? AppTheme.error : AppTheme.textSecondary,
                fontWeight: isOverdue ? FontWeight.w600 : FontWeight.normal,
                fontSize: 13,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildItemsCard(BillModel bill) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Itemized Charges',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
          ),
          const SizedBox(height: 12),
          if (bill.items.isEmpty)
            const Text('No itemized line items found.', style: TextStyle(color: AppTheme.textSecondary))
          else
            ...bill.items.map((item) {
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.chargeHeadName,
                            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                          ),
                          if (item.quantity != null && item.unitRate != null)
                            Text(
                              '${item.quantity} ${item.unitLabel ?? 'units'} × ৳${item.unitRate}',
                              style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                            ),
                          if (item.description != null && item.description != item.chargeHeadName)
                            Text(
                              item.description!,
                              style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                            ),
                        ],
                      ),
                    ),
                    Text(
                      '৳ ${item.amount}',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                    ),
                  ],
                ),
              );
            }),
        ],
      ),
    );
  }

  Widget _buildSummaryCard(BillModel bill) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Ledger Summary',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
          ),
          const SizedBox(height: 12),
          _buildSummaryRow('This Month Charges', '৳ ${bill.monthCharges ?? bill.totalAmount}'),
          if (bill.arrears != null && bill.arrears != '0.00')
            _buildSummaryRow('Brought Forward Arrears', '৳ ${bill.arrears}'),
          if (bill.advanceHeld != null && bill.advanceHeld != '0.00')
            _buildSummaryRow('Advance Held Adjusted', '- ৳ ${bill.advanceHeld}', isDeduction: true),
          _buildSummaryRow('Amount Paid', '- ৳ ${bill.paidAmount}', isDeduction: true),
          const Divider(height: 20, color: AppTheme.border),
          _buildSummaryRow(
            'Outstanding Due Amount',
            '৳ ${bill.dueAmount}',
            isBold: true,
            highlightColor: bill.isPaid ? AppTheme.success : AppTheme.error,
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryRow(
    String label,
    String value, {
    bool isBold = false,
    bool isDeduction = false,
    Color? highlightColor,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 13,
              color: isBold ? AppTheme.textPrimary : AppTheme.textSecondary,
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: isBold ? 15 : 13,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: highlightColor ??
                  (isDeduction
                      ? AppTheme.success
                      : (isBold ? AppTheme.textPrimary : AppTheme.textPrimary)),
            ),
          ),
        ],
      ),
    );
  }
}
