import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../../core/theme/app_theme.dart';
import '../bloc/payment_submission_bloc.dart';
import '../bloc/payment_submission_event.dart';
import '../bloc/payment_submission_state.dart';
import '../models/payment_model.dart';
import 'submit_payment_screen.dart';

class PaymentHistoryScreen extends StatefulWidget {
  const PaymentHistoryScreen({super.key});

  @override
  State<PaymentHistoryScreen> createState() => _PaymentHistoryScreenState();
}

class _PaymentHistoryScreenState extends State<PaymentHistoryScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => PaymentSubmissionBloc(
        apiClient: context.read<ApiClient>(),
        storageService: context.read<SecureStorageService>(),
      )..add(const PaymentSubmissionsFetchRequested()),
      child: Scaffold(
        backgroundColor: AppTheme.background,
        appBar: AppBar(
          title: const Text('Payments & Receipts'),
          bottom: TabBar(
            controller: _tabController,
            indicatorColor: AppTheme.primary,
            labelColor: AppTheme.primary,
            unselectedLabelColor: AppTheme.textSecondary,
            labelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            tabs: const [
              Tab(text: 'Submissions Queue'),
              Tab(text: 'Posted Receipts'),
            ],
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.add_circle_outline_rounded, color: AppTheme.primary),
              tooltip: 'Submit Payment',
              onPressed: () async {
                final result = await Navigator.of(context).push(
                  MaterialPageRoute(builder: (context) => const SubmitPaymentScreen()),
                );
                if (result == true && context.mounted) {
                  context.read<PaymentSubmissionBloc>().add(const PaymentSubmissionsFetchRequested());
                }
              },
            ),
          ],
        ),
        body: SafeArea(
          bottom: true,
          child: BlocConsumer<PaymentSubmissionBloc, PaymentSubmissionState>(
            listener: (context, state) {
              if (state is PaymentSubmissionError) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(state.message),
                    backgroundColor: AppTheme.error,
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              }
            },
            builder: (context, state) {
              if (state is PaymentSubmissionLoading) {
                return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
              }

              if (state is PaymentSubmissionsLoaded) {
                return TabBarView(
                  controller: _tabController,
                  children: [
                    _buildSubmissionsList(context, state.submissions),
                    _buildReceiptsList(context, state.receipts),
                  ],
                );
              }

              return const SizedBox.shrink();
            },
          ),
        ),
      ),
    );
  }

  Widget _buildSubmissionsList(BuildContext context, List<PaymentSubmissionModel> list) {
    if (list.isEmpty) {
      return RefreshIndicator(
        color: AppTheme.primary,
        onRefresh: () async {
          context.read<PaymentSubmissionBloc>().add(const PaymentSubmissionsFetchRequested());
        },
        child: ListView(
          children: const [
            SizedBox(height: 100),
            Center(
              child: Column(
                children: [
                  Icon(Icons.inventory_2_outlined, size: 56, color: AppTheme.textSecondary),
                  SizedBox(height: 12),
                  Text('No payment submissions yet.', style: TextStyle(color: AppTheme.textSecondary)),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: AppTheme.primary,
      onRefresh: () async {
        context.read<PaymentSubmissionBloc>().add(const PaymentSubmissionsFetchRequested());
      },
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        itemCount: list.length,
        itemBuilder: (context, index) {
          final item = list[index];
          return _buildSubmissionCard(context, item);
        },
      ),
    );
  }

  Widget _buildSubmissionCard(BuildContext context, PaymentSubmissionModel item) {
    Color badgeColor;
    Color badgeBg;
    String statusText = item.status.toUpperCase();

    if (item.isVerified) {
      badgeColor = AppTheme.success;
      badgeBg = AppTheme.successLight;
    } else if (item.isRejected) {
      badgeColor = AppTheme.error;
      badgeBg = AppTheme.errorLight;
    } else {
      badgeColor = AppTheme.warning;
      badgeBg = AppTheme.warningLight;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '৳ ${item.amount}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppTheme.textPrimary),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: badgeBg,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    statusText,
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: badgeColor),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.payment_outlined, size: 16, color: AppTheme.textSecondary),
                const SizedBox(width: 6),
                Text(
                  item.paymentMethod,
                  style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                ),
                const SizedBox(width: 14),
                const Icon(Icons.tag_rounded, size: 16, color: AppTheme.textSecondary),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    item.referenceNumber,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                  ),
                ),
              ],
            ),
            if (item.isRejected && item.rejectionReason != null) ...[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppTheme.errorLight.withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppTheme.error.withValues(alpha: 0.3)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.info_outline_rounded, size: 16, color: AppTheme.error),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Reason: ${item.rejectionReason}',
                        style: const TextStyle(fontSize: 12, color: AppTheme.error),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Submitted: ${item.createdAt.split('T').first}',
                  style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                ),
                if (item.slipUrl != null)
                  TextButton.icon(
                    style: TextButton.styleFrom(
                      padding: EdgeInsets.zero,
                      minimumSize: const Size(50, 30),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    icon: const Icon(Icons.attachment_rounded, size: 16),
                    label: const Text('View Slip', style: TextStyle(fontSize: 12)),
                    onPressed: () {
                      _openUrl(item.slipUrl!);
                    },
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildReceiptsList(BuildContext context, List<PaymentReceiptModel> list) {
    if (list.isEmpty) {
      return RefreshIndicator(
        color: AppTheme.primary,
        onRefresh: () async {
          context.read<PaymentSubmissionBloc>().add(const PaymentSubmissionsFetchRequested());
        },
        child: ListView(
          children: const [
            SizedBox(height: 100),
            Center(
              child: Column(
                children: [
                  Icon(Icons.receipt_outlined, size: 56, color: AppTheme.textSecondary),
                  SizedBox(height: 12),
                  Text('No posted receipts found.', style: TextStyle(color: AppTheme.textSecondary)),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: AppTheme.primary,
      onRefresh: () async {
        context.read<PaymentSubmissionBloc>().add(const PaymentSubmissionsFetchRequested());
      },
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        itemCount: list.length,
        itemBuilder: (context, index) {
          final receipt = list[index];
          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppTheme.successLight,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.check_circle_outline_rounded, color: AppTheme.success, size: 24),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Payment #${receipt.paymentNo}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.textPrimary),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${receipt.paymentDate} • ${receipt.paymentMethod}',
                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '৳ ${receipt.amount}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.primary),
                        ),
                      ],
                    ),
                  ),
                  if (receipt.receiptUrl != null)
                    IconButton(
                      icon: const Icon(Icons.download_rounded, color: AppTheme.primary),
                      tooltip: 'Download Receipt',
                      onPressed: () => _openUrl(receipt.receiptUrl!),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _openUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }
}
