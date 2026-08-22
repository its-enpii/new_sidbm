import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/utils/thermal_printer_service.dart';
import '../../domain/entities/collection_entities.dart';
import '../../domain/repositories/collection_repository.dart';
import '../datasources/collection_remote_datasource.dart';

class CollectionRepositoryImpl implements CollectionRepository {
  final CollectionRemoteDataSource remoteDataSource;

  CollectionRepositoryImpl({required this.remoteDataSource});

  @override
  Future<List<CollectionLoanItem>> searchLoans({String? search, int? villageId}) async {
    try {
      return await remoteDataSource.searchLoans(search: search, villageId: villageId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<LoanCollectionDetail> getLoanDetail(int loanId) async {
    try {
      return await remoteDataSource.getLoanDetail(loanId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<ReceiptData> payInstallment({
    required int loanId,
    required int memberId,
    required double principalAmount,
    required double interestAmount,
    double penaltyAmount = 0,
    int? cashAccountId,
    String? description,
  }) async {
    try {
      return await remoteDataSource.payInstallment(
        loanId: loanId,
        memberId: memberId,
        principalAmount: principalAmount,
        interestAmount: interestAmount,
        penaltyAmount: penaltyAmount,
        cashAccountId: cashAccountId,
        description: description,
      );
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }
}

