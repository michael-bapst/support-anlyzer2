use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SolutionMatchController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ErrorStatsController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\EntryController;

Route::get('/match', [SolutionMatchController::class, 'match']);
Route::get('/search', [SearchController::class, 'search']);
Route::get('/errors/top', [ErrorStatsController::class, 'index']);

Route::get('/cases', [CaseController::class, 'index']);
Route::get('/entries', [EntryController::class, 'index']);
Route::get('/entries/timeline', [EntryController::class, 'timeline']);
