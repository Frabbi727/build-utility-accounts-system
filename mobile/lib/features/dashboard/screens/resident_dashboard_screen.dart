import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../bloc/dashboard_bloc.dart';
import '../bloc/dashboard_event.dart';
import '../models/dashboard_state.dart';

class ResidentDashboardScreen extends StatelessWidget {
  final Function(int tabIndex)? onNavigateToTab;
  final VoidCallback? onSubmitPayment;

  const ResidentDashboardScreen({
    super.key,
    this.onNavigateToTab,
    this.onSubmitPayment,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Resident Portal'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
            onPressed: () {
              context.read<DashboardBloc>().add(DashboardRefreshRequested());
            },
          ),
        ],
      ),
      body: SafeArea(
        bottom: true,
        child: BlocConsumer<DashboardBloc, DashboardState>(
          listener: (context, state) {
            if (state is DashboardError) {
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
            if (state is DashboardLoading) {
              return const Center(
                child: CircularProgressIndicator(color: AppTheme.primary),
              );
            }

            if (state is DashboardError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.cloud_off_rounded, size: 56, color: AppTheme.textSecondary),
                      const SizedBox(height: 16),
                      Text(
                        state.message,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 16, color: AppTheme.textSecondary),
                      ),
                      const SizedBox(height: 20),
                      AppButton(
                        text: 'Try Again',
                        width: 160,
                        onPressed: () {
                          context.read<DashboardBloc>().add(DashboardRefreshRequested());
                        },
                      ),
                    ],
                  ),
                ),
              );
            }

            if (state is DashboardLoaded) {
              final data = state.dashboardData;
              final balances = data.balances;

              return RefreshIndicator(
                color: AppTheme.primary,
                onRefresh: () async {
                  context.read<DashboardBloc>().add(DashboardRefreshRequested());
                },
                child: ListView(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: [
                    // Flat Switcher Dropdown
                    if (state.userFlats.length > 1) ...[
                      _buildFlatSwitcher(context, state),
                      const SizedBox(height: 12),
                    ] else ...[
                      _buildSingleFlatHeader(state.selectedFlat),
                      const SizedBox(height: 12),
                    ],

                    // Dynamic Live Balances Card
                    _buildBalancesCard(context, balances),
                    const SizedBox(height: 16),

                    // Quick Action Shortcuts
                    _buildActionShortcuts(context),
                    const SizedBox(height: 16),

                    // Latest Bill Card
                    if (data.latestBill != null) ...[
                      _buildLatestBillCard(context, data.latestBill!),
                      const SizedBox(height: 16),
                    ],

                    // Active Notices
                    if (data.activeNotices.isNotEmpty) ...[
                      _buildNoticesSnippet(context, data.activeNotices),
                      const SizedBox(height: 16),
                    ],

                    // Safety padding for bottom navigation
                    const SizedBox(height: 32),
                  ],
                ),
              );
            }

            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  Widget _buildSingleFlatHeader(FlatItem flat) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppTheme.border),
      ),
      child: Row(
        children: [
          const Icon(Icons.home_work_rounded, color: AppTheme.primary, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Flat ${flat.number} • ${flat.buildingName}',
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 15,
                color: AppTheme.textPrimary,
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: AppTheme.primaryLight,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              flat.role.toUpperCase(),
              style: const TextStyle(
                color: AppTheme.primaryDark,
                fontSize: 11,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFlatSwitcher(BuildContext context, DashboardLoaded state) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppTheme.border),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: state.selectedFlat.id,
          isExpanded: true,
          icon: const Icon(Icons.swap_horiz_rounded, color: AppTheme.primary),
          items: state.userFlats.map((flat) {
            return DropdownMenuItem<int>(
              value: flat.id,
              child: Row(
                children: [
                  const Icon(Icons.apartment_rounded, size: 18, color: AppTheme.textSecondary),
                  const SizedBox(width: 8),
                  Text(
                    'Flat ${flat.number} (${flat.buildingName})',
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '[${flat.role}]',
                    style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                  ),
                ],
              ),
            );
          }).toList(),
          onChanged: (flatId) {
            if (flatId != null && flatId != state.selectedFlat.id) {
              context.read<DashboardBloc>().add(DashboardFetchRequested(flatId: flatId));
            }
          },
        ),
      ),
    );
  }

  Widget _buildBalancesCard(BuildContext context, ResidentBalances balances) {
    final hasDue = balances.hasOutstandingDue;

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: hasDue
              ? [const Color(0xFF0F766E), const Color(0xFF0D9488)]
              : [const Color(0xFF1E293B), const Color(0xFF334155)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: (hasDue ? AppTheme.primary : Colors.black).withValues(alpha: 0.2),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Total Outstanding Due',
                style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Text(
                  'Live Ledger',
                  style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '৳ ${balances.totalDue}',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 30,
              fontWeight: FontWeight.bold,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 16),
          const Divider(color: Colors.white24, height: 1),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Advance Held',
                      style: TextStyle(color: Colors.white70, fontSize: 11),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '৳ ${balances.advanceHeld}',
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Current Month',
                      style: TextStyle(color: Colors.white70, fontSize: 11),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '৳ ${balances.currentMonthCharges}',
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Arrears',
                      style: TextStyle(color: Colors.white70, fontSize: 11),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '৳ ${balances.arrears}',
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildActionShortcuts(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _buildShortcutButton(
            icon: Icons.payments_rounded,
            label: 'Submit Payment',
            color: AppTheme.primary,
            onTap: () {
              if (onSubmitPayment != null) {
                onSubmitPayment!();
              } else if (onNavigateToTab != null) {
                onNavigateToTab!(2); // Payments tab
              }
            },
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _buildShortcutButton(
            icon: Icons.receipt_long_rounded,
            label: 'View Bills',
            color: AppTheme.secondary,
            onTap: () {
              if (onNavigateToTab != null) {
                onNavigateToTab!(1); // Bills tab
              }
            },
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _buildShortcutButton(
            icon: Icons.build_circle_rounded,
            label: 'Support / Ticket',
            color: AppTheme.warning,
            onTap: () {
              if (onNavigateToTab != null) {
                onNavigateToTab!(3); // Tickets tab
              }
            },
          ),
        ),
      ],
    );
  }

  Widget _buildShortcutButton({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        decoration: BoxDecoration(
          color: AppTheme.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppTheme.border),
        ),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(height: 8),
            Text(
              label,
              textAlign: TextAlign.center,
              maxLines: 2,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLatestBillCard(BuildContext context, Map<String, dynamic> bill) {
    final status = bill['status']?.toString() ?? 'unpaid';
    final isPaid = status == 'paid';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Latest Monthly Bill',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: isPaid ? AppTheme.successLight : AppTheme.errorLight,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  status.toUpperCase(),
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: isPaid ? AppTheme.success : AppTheme.error,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Bill #${bill['bill_no'] ?? ''}',
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
              ),
              Text(
                '৳ ${bill['total_amount'] ?? '0.00'}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
              ),
            ],
          ),
          if (bill['due_date'] != null) ...[
            const SizedBox(height: 4),
            Text(
              'Due Date: ${bill['due_date']}',
              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildNoticesSnippet(BuildContext context, List<dynamic> notices) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Announcements',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
            ),
            TextButton(
              onPressed: () {
                if (onNavigateToTab != null) {
                  onNavigateToTab!(4); // Notices tab
                }
              },
              child: const Text('See all'),
            ),
          ],
        ),
        ...notices.take(2).map((notice) {
          final title = notice['title']?.toString() ?? 'Notice';
          final isPinned = notice['is_pinned'] == true;
          return Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppTheme.surface,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppTheme.border),
            ),
            child: Row(
              children: [
                Icon(
                  isPinned ? Icons.push_pin_rounded : Icons.notifications_none_rounded,
                  color: isPinned ? AppTheme.warning : AppTheme.primary,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                ),
              ],
            ),
          );
        }),
      ],
    );
  }
}
