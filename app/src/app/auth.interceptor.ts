import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';
import { isAppApi } from './config';

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  if (!isAppApi(request.url)) return next(request);
  const auth = inject(AuthService);
  const router = inject(Router);
  const headers: Record<string, string> = { 'X-FR-Request': '1' };
  if (auth.csrf()) headers['X-FR-CSRF'] = auth.csrf();
  return next(request.clone({ withCredentials: true, setHeaders: headers })).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401) {
        const wasAuthenticated = !!auth.user();
        auth.clear();
        if (wasAuthenticated && !router.url.startsWith('/login')) void router.navigateByUrl('/login');
      }
      return throwError(() => error);
    })
  );
};
