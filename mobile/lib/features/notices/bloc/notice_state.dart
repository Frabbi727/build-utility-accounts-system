import 'package:equatable/equatable.dart';
import '../models/notice_model.dart';

abstract class NoticeState extends Equatable {
  const NoticeState();

  @override
  List<Object?> get props => [];
}

class NoticeInitial extends NoticeState {}

class NoticeLoading extends NoticeState {}

class NoticeLoaded extends NoticeState {
  final List<NoticeModel> notices;

  const NoticeLoaded(this.notices);

  @override
  List<Object?> get props => [notices];
}

class NoticeError extends NoticeState {
  final String message;

  const NoticeError(this.message);

  @override
  List<Object?> get props => [message];
}
