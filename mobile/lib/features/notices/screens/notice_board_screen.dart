import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/app_theme.dart';
import '../bloc/notice_bloc.dart';
import '../bloc/notice_event.dart';
import '../bloc/notice_state.dart';
import '../models/notice_model.dart';
import 'notice_detail_screen.dart';

class NoticeBoardScreen extends StatelessWidget {
  const NoticeBoardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => NoticeBloc(
        apiClient: context.read<ApiClient>(),
      )..add(NoticesFetchRequested()),
      child: const _NoticeBoardView(),
    );
  }
}

class _NoticeBoardView extends StatelessWidget {
  const _NoticeBoardView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Building Notice Board'),
      ),
      body: SafeArea(
        bottom: true,
        child: BlocConsumer<NoticeBloc, NoticeState>(
          listener: (context, state) {
            if (state is NoticeError) {
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
            if (state is NoticeLoading) {
              return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
            }

            if (state is NoticeLoaded) {
              final notices = state.notices;
              if (notices.isEmpty) {
                return RefreshIndicator(
                  color: AppTheme.primary,
                  onRefresh: () async {
                    context.read<NoticeBloc>().add(NoticesFetchRequested());
                  },
                  child: ListView(
                    children: const [
                      SizedBox(height: 120),
                      Center(
                        child: Column(
                          children: [
                            Icon(Icons.campaign_outlined, size: 56, color: AppTheme.textSecondary),
                            SizedBox(height: 12),
                            Text('No announcements published yet.', style: TextStyle(color: AppTheme.textSecondary)),
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
                  context.read<NoticeBloc>().add(NoticesFetchRequested());
                },
                child: ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  itemCount: notices.length,
                  itemBuilder: (context, index) {
                    final notice = notices[index];
                    return _buildNoticeCard(context, notice);
                  },
                ),
              );
            }

            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  Widget _buildNoticeCard(BuildContext context, NoticeModel notice) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (context) => NoticeDetailScreen(notice: notice),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  if (notice.isPinned) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.warningLight,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.push_pin_rounded, size: 12, color: AppTheme.warning),
                          SizedBox(width: 4),
                          Text(
                            'PINNED',
                            style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.warning),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Text(
                    notice.publishDate,
                    style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                notice.title,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
              ),
              const SizedBox(height: 6),
              Text(
                notice.content,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13, height: 1.4),
              ),
              const SizedBox(height: 10),
              const Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text(
                    'Read more',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.primary),
                  ),
                  Icon(Icons.chevron_right_rounded, size: 16, color: AppTheme.primary),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
