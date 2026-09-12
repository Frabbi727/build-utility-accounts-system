import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../../core/theme/app_theme.dart';
import '../bloc/bills_bloc.dart';
import '../bloc/bills_event.dart';
import '../bloc/bills_state.dart';
import '../models/bill_model.dart';
import 'bill_detail_screen.dart';

class BillsListScreen extends StatefulWidget {
  const BillsListScreen({super.key});

  @override
  State<BillsListScreen> createState() => _BillsListScreenState();
}

class _BillsListScreenState extends State<BillsListScreen> {
  String? _selectedStatus; // null for all, 'unpaid', 'paid'

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => BillsBloc(
        apiClient: context.read<ApiClient>(),
        storageService: context.read<SecureStorageService>(),
      )..add(BillsFetchRequested(status: _selectedStatus)),
      child: Scaffold(
        backgroundColor: AppTheme.background,
        appBar: AppBar(
          title: const Text('Utility Bills'),
        ),
        body: SafeArea(
          bottom: true,
          child: Column(
            children: [
              // Filter Chips
              Container(
                color: AppTheme.surface,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Row(
                  children: [
                    _buildFilterChip(context, label: 'All Bills', status: null),
                    const SizedBox(width: 8),
                    _buildFilterChip(context, label: 'Unpaid / Due', status: 'unpaid'),
                    const SizedBox(width: 8),
                    _buildFilterChip(context, label: 'Paid', status: 'paid'),
                  ],
                ),
              ),
              const Divider(height: 1, color: AppTheme.border),

              // Bills List
              Expanded(
                child: BlocConsumer<BillsBloc, BillsState>(
                  listener: (context, state) {
                    if (state is BillsError) {
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
                    if (state is BillsLoading) {
                      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
                    }

                    if (state is BillsLoaded) {
                      final bills = state.bills;
                      if (bills.isEmpty) {
                        return Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.receipt_long_outlined, size: 56, color: AppTheme.textSecondary),
                              const SizedBox(height: 12),
                              Text(
                                _selectedStatus == null
                                    ? 'No bills found for this flat.'
                                    : 'No $_selectedStatus bills found.',
                                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 15),
                              ),
                            ],
                          ),
                        );
                      }

                      return RefreshIndicator(
                        color: AppTheme.primary,
                        onRefresh: () async {
                          context.read<BillsBloc>().add(BillsFetchRequested(status: _selectedStatus));
                        },
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          itemCount: bills.length,
                          itemBuilder: (context, index) {
                            final bill = bills[index];
                            return _buildBillCard(context, bill);
                          },
                        ),
                      );
                    }

                    return const SizedBox.shrink();
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFilterChip(BuildContext context, {required String label, required String? status}) {
    final isSelected = _selectedStatus == status;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (selected) {
        setState(() {
          _selectedStatus = status;
        });
        context.read<BillsBloc>().add(BillsFetchRequested(status: _selectedStatus));
      },
      selectedColor: AppTheme.primaryLight,
      labelStyle: TextStyle(
        color: isSelected ? AppTheme.primaryDark : AppTheme.textSecondary,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
        fontSize: 13,
      ),
      side: BorderSide(
        color: isSelected ? AppTheme.primary : AppTheme.border,
      ),
      backgroundColor: AppTheme.surface,
    );
  }

  Widget _buildBillCard(BuildContext context, BillModel bill) {
    final isPaid = bill.isPaid;
    final isOverdue = bill.isOverdue;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (context) => BillDetailScreen(billId: bill.id),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    bill.billingMonthFormatted,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: isPaid
                          ? AppTheme.successLight
                          : (isOverdue ? AppTheme.errorLight : AppTheme.warningLight),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      bill.status.toUpperCase(),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: isPaid
                            ? AppTheme.success
                            : (isOverdue ? AppTheme.error : AppTheme.warning),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Bill #${bill.billNo}',
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
              ),
              const SizedBox(height: 12),
              const Divider(height: 1, color: AppTheme.divider),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Total Bill', style: TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                      const SizedBox(height: 2),
                      Text('৳ ${bill.totalAmount}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                    ],
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        isPaid ? 'Amount Paid' : 'Due Amount',
                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '৳ ${isPaid ? bill.paidAmount : bill.dueAmount}',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                          color: isPaid ? AppTheme.success : AppTheme.error,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
